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
 * Reports page for local_competencymanager
 * VERSIONE COMPLETA E CORRETTA
 * 
 * Funzionalità:
 * - Nomi competenze in ITALIANO (da database)
 * - Raggruppamento per AREE
 * - Soglie configurabili
 * - Spunti colloquio con domande personalizzate
 * - GIUSTIFICAZIONE: mostra quiz, domande sbagliate, risposte
 * - Link a CompetencyManager
 * - Grafica migliorata con colori evidenti
 *
 * @package    local_competencymanager
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/coach_manager.php');
require_once(__DIR__ . '/classes/assessment_manager.php');

use local_competencymanager\coach_manager;
use local_competencymanager\assessment_manager;

// Richiedi login
require_login();

// Verifica permessi
$context = context_system::instance();
if (!has_capability('moodle/course:manageactivities', $context) && !is_siteadmin()) { throw new moodle_exception('nopermissions', 'error', '', 'view reports'); }

// Parametri
$studentid = optional_param('studentid', 0, PARAM_INT);
$reporttype = optional_param('type', 'overview', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);

// ============================================
// SOGLIE CONFIGURABILI DAL COACH
// ============================================
$sogliaAllineamento = optional_param('soglia_allineamento', 15, PARAM_INT);
$sogliaAllineamento = max(5, min(40, $sogliaAllineamento));

$sogliaCritico = optional_param('soglia_critico', 30, PARAM_INT);
$sogliaCritico = max(20, min(60, $sogliaCritico));

if ($sogliaCritico <= $sogliaAllineamento) {
    $sogliaCritico = $sogliaAllineamento + 15;
}
// ============================================

// Setup pagina
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/reports.php', 
    $studentid ? ['studentid' => $studentid] : []));
$PAGE->set_title(get_string('reports', 'local_competencymanager'));
$PAGE->set_heading(get_string('reports', 'local_competencymanager'));
$PAGE->set_pagelayout('standard');

// Output
echo $OUTPUT->header();

// Tab di navigazione
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/competencymanager/index.php', ['courseid' => $courseid]), 'Dashboard'),
    new tabobject('students', new moodle_url('/local/competencymanager/students.php', ['courseid' => $courseid]), 'Studenti'),
    new tabobject('selfassessments', new moodle_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]), 'Autovalutazioni'),
    new tabobject('reports', new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]), 'Report'),
];
echo $OUTPUT->tabtree($tabs, 'reports');

$userid = $USER->id;
$bloomlevels = local_competencymanager_get_bloom_levels('it');

// ============================================
// MAPPA AREE CON NOMI ITALIANI
// ============================================
$areaNames = [
    // MECCANICA - Lavorazioni
    'LMB' => ['name' => 'Lavorazioni Manuali di Base', 'icon' => '🔧', 'color' => '#795548', 'sector' => 'MECCANICA'],
    'LMC' => ['name' => 'Lavorazioni Macchine Convenzionali', 'icon' => '⚙️', 'color' => '#607d8b', 'sector' => 'MECCANICA'],
    'CNC' => ['name' => 'Controllo Numerico CNC', 'icon' => '🖥️', 'color' => '#00bcd4', 'sector' => 'MECCANICA'],
    'GEN' => ['name' => 'Lavorazioni Generali', 'icon' => '🏭', 'color' => '#9e9e9e', 'sector' => 'MECCANICA'],
    
    // MECCANICA - Progettazione e Pianificazione
    'DT' => ['name' => 'Disegno Tecnico', 'icon' => '📐', 'color' => '#3498db', 'sector' => 'MECCANICA'],
    'PIAN' => ['name' => 'Pianificazione', 'icon' => '📋', 'color' => '#9b59b6', 'sector' => 'MECCANICA'],
    'PRG' => ['name' => 'Programmazione e Progettazione', 'icon' => '💻', 'color' => '#2ecc71', 'sector' => 'MECCANICA'],
    
    // MECCANICA - Montaggio e Manutenzione
    'ASS' => ['name' => 'Assemblaggio', 'icon' => '🔩', 'color' => '#f39c12', 'sector' => 'MECCANICA'],
    'MAN' => ['name' => 'Manutenzione', 'icon' => '🔨', 'color' => '#e67e22', 'sector' => 'MECCANICA'],
    'AUT' => ['name' => 'Automazione', 'icon' => '🤖', 'color' => '#e74c3c', 'sector' => 'MECCANICA'],
    
    // MECCANICA - Controllo e Sicurezza
    'MIS' => ['name' => 'Misurazione', 'icon' => '📏', 'color' => '#1abc9c', 'sector' => 'MECCANICA'],
    'SAQ' => ['name' => 'Sicurezza, Ambiente e Qualità', 'icon' => '🛡️', 'color' => '#c0392b', 'sector' => 'MECCANICA'],
    
    // MECCANICA - Competenze Trasversali
    'CSP' => ['name' => 'Collaborazione e Sviluppo Personale', 'icon' => '🤝', 'color' => '#8e44ad', 'sector' => 'MECCANICA'],
    
    // AUTOMOBILE - Diagnosi e Manutenzione
    'MAu' => ['name' => 'Manutenzione Auto', 'icon' => '🚗', 'color' => '#3498db', 'sector' => 'AUTOMOBILE'],
    'MR' => ['name' => 'Manutenzione e Riparazione', 'icon' => '🔧', 'color' => '#e74c3c', 'sector' => 'AUTOMOBILE'],
    'DG' => ['name' => 'Diagnosi', 'icon' => '🔍', 'color' => '#9b59b6', 'sector' => 'AUTOMOBILE'],
    
    // AUTOMOBILE - Sistemi
    'EL' => ['name' => 'Elettronica', 'icon' => '⚡', 'color' => '#f1c40f', 'sector' => 'AUTOMOBILE'],
    'MO' => ['name' => 'Motore', 'icon' => '🏎️', 'color' => '#e67e22', 'sector' => 'AUTOMOBILE'],
    'TR' => ['name' => 'Trasmissione', 'icon' => '⚙️', 'color' => '#1abc9c', 'sector' => 'AUTOMOBILE'],
    'FR' => ['name' => 'Freni', 'icon' => '🛑', 'color' => '#c0392b', 'sector' => 'AUTOMOBILE'],
    'SO' => ['name' => 'Sospensioni', 'icon' => '🔩', 'color' => '#7f8c8d', 'sector' => 'AUTOMOBILE'],
    'CL' => ['name' => 'Climatizzazione', 'icon' => '❄️', 'color' => '#00bcd4', 'sector' => 'AUTOMOBILE'],
    'CA' => ['name' => 'Carrozzeria', 'icon' => '🚙', 'color' => '#795548', 'sector' => 'AUTOMOBILE'],
    
    
    // CHIMFARM - Chimica Farmaceutica
    '1C' => ['name' => 'Conformità e GMP', 'icon' => '✅', 'color' => '#4CAF50', 'sector' => 'CHIMFARM'],
    '1G' => ['name' => 'Gestione Materiali', 'icon' => '📦', 'color' => '#795548', 'sector' => 'CHIMFARM'],
    '1O' => ['name' => 'Operazioni Base', 'icon' => '⚗️', 'color' => '#9C27B0', 'sector' => 'CHIMFARM'],
    '2M' => ['name' => 'Misurazione', 'icon' => '📏', 'color' => '#00BCD4', 'sector' => 'CHIMFARM'],
    '3C' => ['name' => 'Controllo Qualità', 'icon' => '🔬', 'color' => '#3F51B5', 'sector' => 'CHIMFARM'],
    '4S' => ['name' => 'Sicurezza', 'icon' => '🛡️', 'color' => '#F44336', 'sector' => 'CHIMFARM'],
    '5S' => ['name' => 'Sterilità', 'icon' => '🧪', 'color' => '#E91E63', 'sector' => 'CHIMFARM'],
    '6P' => ['name' => 'Produzione', 'icon' => '🏭', 'color' => '#FF9800', 'sector' => 'CHIMFARM'],
    '7S' => ['name' => 'Strumentazione', 'icon' => '🔧', 'color' => '#607D8B', 'sector' => 'CHIMFARM'],
    '8T' => ['name' => 'Tecnologie', 'icon' => '💻', 'color' => '#009688', 'sector' => 'CHIMFARM'],
    '9A' => ['name' => 'Analisi', 'icon' => '📊', 'color' => '#673AB7', 'sector' => 'CHIMFARM'],
// Default
    'OTHER' => ['name' => 'Altro', 'icon' => '📁', 'color' => '#95a5a6', 'sector' => 'GENERICO'],
];

// ============================================
// PARAMETRI AREE VISIBILI (dal docente)
// ============================================
// Legge quali aree mostrare dalla URL (default: tutte)
$selectedAreasParam = optional_param('aree', '', PARAM_TEXT);
$selectedAreas = [];
if (!empty($selectedAreasParam)) {
    $selectedAreas = explode(',', $selectedAreasParam);
    $selectedAreas = array_filter($selectedAreas); // Rimuovi vuoti
}
// Se vuoto, mostra tutte
$showAllAreas = empty($selectedAreas);

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Estrae l'area dal codice competenza
 * Formato: SETTORE_AREA_NUMERO (es. MECCANICA_DT_03, AUTOMOBILE_MAu_J3)
 */
function extract_area_from_code($code) {
    $parts = explode('_', $code);
    if (count($parts) >= 2) {
        return $parts[1]; // Ritorna la seconda parte (es. DT, MAu, MR)
    }
    return 'OTHER';
}

/**
 * Estrae il settore dal codice competenza
 */
function extract_sector_from_code($code) {
    $parts = explode('_', $code);
    if (count($parts) >= 1) {
        return $parts[0]; // Ritorna la prima parte (es. MECCANICA, AUTOMOBILE)
    }
    return 'GENERICO';
}

/**
 * Ottiene il nome italiano della competenza dal database
 */
function get_competency_italian_name($competencyid, $idnumber = null) {
    global $DB;
    
    // Prima prova con l'ID
    if ($competencyid) {
        $competency = $DB->get_record('competency', ['id' => $competencyid], 'id, shortname, description, idnumber');
        if ($competency) {
            // Preferisce description se disponibile e non vuota, altrimenti shortname
            if (!empty($competency->description)) {
                // Rimuovi tag HTML dalla descrizione
                $desc = strip_tags($competency->description);
                // Tronca se troppo lunga
                if (strlen($desc) > 100) {
                    $desc = substr($desc, 0, 100) . '...';
                }
                return $desc;
            }
            if (!empty($competency->shortname)) {
                return $competency->shortname;
            }
        }
    }
    
    // Prova con idnumber
    if ($idnumber) {
        $competency = $DB->get_record_sql("SELECT id, shortname, description FROM {competency} WHERE idnumber = ? ORDER BY id DESC LIMIT 1", [$idnumber]);
        if ($competency) {
            if (!empty($competency->description)) {
                $desc = strip_tags($competency->description);
                if (strlen($desc) > 100) {
                    $desc = substr($desc, 0, 100) . '...';
                }
                return $desc;
            }
            if (!empty($competency->shortname)) {
                return $competency->shortname;
            }
        }
    }
    
    return null; // Non trovato
}

/**
 * Rileva il nome della tabella che collega domande a competenze
 * Usa una query SQL diretta per verificare se la tabella esiste REALMENTE
 */
function get_question_competency_table() {
    global $DB, $CFG;
    
    // Cache statica per non ripetere il check
    static $cachedTable = null;
    static $checked = false;
    
    if ($checked) {
        return $cachedTable;
    }
    
    $checked = true;
    
    // Lista di possibili nomi tabella (in ordine di priorità)
    $possibleTables = [
        'qbank_comp_question',           // Plugin qbank_competenciesbyquestion
        'qbank_competenciesbyquestion',  // Nome alternativo
        'local_competencymanager_qc',    // Plugin CompetencyManager
        'qbank_competencies',            // Plugin qbank_competencies
        'question_competencies',         // Nome generico
    ];
    
    // Verifica usando query SQL diretta (più affidabile di $dbman->table_exists)
    foreach ($possibleTables as $tableName) {
        $fullTableName = $CFG->prefix . $tableName;
        
        try {
            // Prova una query semplice sulla tabella
            // Se fallisce, la tabella non esiste
            $DB->get_record_sql("SELECT 1 FROM {{$tableName}} LIMIT 1", [], IGNORE_MISSING);
            
            // Se arriviamo qui, la tabella esiste!
            $cachedTable = $tableName;
            return $cachedTable;
            
        } catch (Exception $e) {
            // Tabella non esiste, prova la prossima
            continue;
        } catch (dml_exception $e) {
            // Tabella non esiste, prova la prossima
            continue;
        }
    }
    
    // Nessuna tabella trovata
    return null;
}

/**
 * Ottiene le informazioni dettagliate sulle risposte dello studente per una competenza
 * GIUSTIFICAZIONE: mostra quiz, domande sbagliate, risposte date
 * NOTA: Gestisce silenziosamente gli errori senza mostrare nulla all'utente
 */
function get_competency_justification($studentid, $competencyid, $courseid = null) {
    global $DB;
    
    $justification = [
        'quizzes' => [],
        'total_questions' => 0,
        'correct_questions' => 0,
        'wrong_questions' => [],
        'attempts' => [],
        'table_missing' => false
    ];
    
    // Verifica quale tabella usare per il collegamento domande-competenze
    $qcTable = get_question_competency_table();
    
    if (!$qcTable) {
        // Nessuna tabella trovata - giustificazioni non disponibili
        $justification['table_missing'] = true;
        return $justification;
    }
    
    // Query per ottenere tutte le domande relative a questa competenza
    $params = ['studentid' => $studentid, 'competencyid' => $competencyid];
    $courseCondition = '';
    if ($courseid) {
        $courseCondition = 'AND q.course = :courseid';
        $params['courseid'] = $courseid;
    }
    
    // Costruisci query con il nome tabella corretto
    $sql = "SELECT 
                qat.id as unique_row_id, qat.id as unique_row_id, qat.id as unique_row_id, qa.id as attemptid,
                qa.attempt as attemptnumber,
                qa.sumgrades,
                qa.timefinish,
                q.id as quizid,
                q.name as quizname,
                q.grade as quizgrade,
                qat.id as questionattemptid,
                qat.questionid,
                qat.maxmark,
                que.name as questionname,
                que.questiontext,
                (SELECT MAX(qas.fraction) 
                 FROM {question_attempt_steps} qas 
                 WHERE qas.questionattemptid = qat.id 
                 AND qas.fraction IS NOT NULL) as fraction,
                (SELECT qas.state
                 FROM {question_attempt_steps} qas 
                 WHERE qas.questionattemptid = qat.id 
                 ORDER BY qas.sequencenumber DESC LIMIT 1) as finalstate
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON qa.quiz = q.id
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} que ON que.id = qat.questionid
            JOIN {{$qcTable}} qcq ON qcq.questionid = qat.questionid
            WHERE qa.userid = :studentid
            AND qa.state = 'finished'
            AND qcq.competencyid = :competencyid
            {$courseCondition}
            ORDER BY qa.timefinish DESC, qat.slot ASC";
    
    try {
        $results = $DB->get_records_sql($sql, $params);
        
        $quizzes = [];
        foreach ($results as $row) {
            $justification['total_questions']++;
            
            $fraction = $row->fraction ?? 0;
            $isCorrect = ($fraction >= 0.5);
            
            if ($isCorrect) {
                $justification['correct_questions']++;
            }
            
            // Raggruppa per quiz
            if (!isset($quizzes[$row->quizid])) {
                $quizzes[$row->quizid] = [
                    'id' => $row->quizid,
                    'name' => $row->quizname,
                    'total' => 0,
                    'correct' => 0,
                    'wrong' => [],
                    'attempt_url' => new moodle_url('/mod/quiz/review.php', ['attempt' => $row->attemptid])
                ];
            }
            
            $quizzes[$row->quizid]['total']++;
            
            if ($isCorrect) {
                $quizzes[$row->quizid]['correct']++;
            } else {
                // Domanda sbagliata - salva dettagli
                $wrongQuestion = [
                    'id' => $row->questionid,
                    'name' => $row->questionname,
                    'text' => strip_tags(substr($row->questiontext, 0, 200)),
                    'fraction' => round($fraction * 100),
                    'state' => $row->finalstate
                ];
                
                // Prova a ottenere la risposta data
                $wrongQuestion['student_answer'] = get_student_answer($row->questionattemptid);
                
                $quizzes[$row->quizid]['wrong'][] = $wrongQuestion;
                $justification['wrong_questions'][] = $wrongQuestion;
            }
        }
        
        $justification['quizzes'] = array_values($quizzes);
        
    } catch (Exception $e) {
        // Se la query fallisce, la tabella potrebbe non esistere
        // Ritorna silenziosamente con table_missing = true
        $justification['table_missing'] = true;
    } catch (dml_exception $e) {
        // Errore database - gestisci silenziosamente
        $justification['table_missing'] = true;
    }
    
    return $justification;
}

/**
 * Ottiene la risposta data dallo studente per una question_attempt
 */
function get_student_answer($questionattemptid) {
    global $DB;
    
    try {
        // Prova a ottenere la risposta dal campo 'answer' degli step
        $sql = "SELECT qasd.value
                FROM {question_attempt_step_data} qasd
                JOIN {question_attempt_steps} qas ON qas.id = qasd.attemptstepid
                WHERE qas.questionattemptid = :qaid
                AND qasd.name = 'answer'
                ORDER BY qas.sequencenumber DESC
                LIMIT 1";
        
        $answer = $DB->get_field_sql($sql, ['qaid' => $questionattemptid]);
        
        if ($answer !== false) {
            return strip_tags($answer);
        }
        
        // Prova con 'choice' per domande a scelta multipla
        $sql = "SELECT qasd.value
                FROM {question_attempt_step_data} qasd
                JOIN {question_attempt_steps} qas ON qas.id = qasd.attemptstepid
                WHERE qas.questionattemptid = :qaid
                AND qasd.name LIKE 'choice%'
                ORDER BY qas.sequencenumber DESC
                LIMIT 1";
        
        $answer = $DB->get_field_sql($sql, ['qaid' => $questionattemptid]);
        
        if ($answer !== false) {
            return "Opzione " . ($answer + 1);
        }
        
    } catch (Exception $e) {
        // Ignora errori
    }
    
    return null;
}

/**
 * Genera spunti colloquio arricchiti con domande esplorative
 * Usa nomi italiani e include giustificazioni
 */
function generate_enhanced_hints($gaps, $sogliaAllineamento, $sogliaCritico, $bloomlevels, $areaNames, $studentid, $courseid = null) {
    global $DB;
    
    $hints = [
        'critici' => [],
        'attenzione' => [],
        'positivi' => [],
        'esplorativi' => []
    ];
    
    foreach ($gaps as $competencyid => $gap) {
        // Codice competenza
        $compCode = $gap->competency_idnumber ?: "ID_{$competencyid}";
        
        // Nome italiano - prova dal database
        $compItalianName = get_competency_italian_name($gap->competencyid, $gap->competency_idnumber);
        if (!$compItalianName) {
            $compItalianName = $gap->competency_name ?: $compCode;
        }
        
        // Estrai area
        $areaCode = extract_area_from_code($compCode);
        $areaInfo = $areaNames[$areaCode] ?? $areaNames['OTHER'];
        
        // Calcola gap percentuale
        $autoPerc = isset($gap->self_assessment) && $gap->self_assessment ? 
                    round(($gap->self_assessment / 6) * 100) : null;
        $perfPerc = isset($gap->real_performance_percentage) ? 
                    $gap->real_performance_percentage : 
                    (isset($gap->real_performance) && $gap->real_performance ? 
                     round(($gap->real_performance / 6) * 100) : null);
        
        $gapValue = null;
        if ($autoPerc !== null && $perfPerc !== null) {
            $gapValue = $autoPerc - $perfPerc;
        }
        
        // Ottieni giustificazione (quiz, domande sbagliate, risposte)
        $justification = get_competency_justification($studentid, $competencyid, $courseid);
        
        $hint = [
            'competenza' => $compItalianName,
            'codice' => $compCode,
            'area' => $areaInfo,
            'area_code' => $areaCode,
            'autovalutazione' => $autoPerc,
            'performance' => $perfPerc,
            'gap' => $gapValue,
            'bloom_auto' => $gap->self_assessment ?? null,
            'bloom_perf' => $gap->real_performance ?? null,
            'justification' => $justification,
            'azione' => '' // Default vuoto, sarà riempito sotto
        ];
        
        // Classifica e genera domande
        if ($gapValue !== null) {
            $gapAbs = abs($gapValue);
            
            if ($gapValue > $sogliaCritico) {
                // SOPRAVVALUTAZIONE CRITICA
                $hint['tipo'] = 'sopravvalutazione_critica';
                $hint['icona'] = '🔴';
                $hint['colore'] = '#dc3545';
                $hint['bg'] = '#f8d7da';
                $hint['messaggio'] = "Gap critico: lo studente si percepisce molto più competente di quanto dimostrato nei quiz.";
                $hint['domande'] = [
                    "Come ti senti riguardo a \"{$compItalianName}\"?",
                    "Quali difficoltà hai incontrato durante i quiz su questo argomento?",
                    "Raccontami dove e come hai acquisito questa competenza.",
                    "Che mansioni o esperienze ti hanno fatto sviluppare questa abilità?",
                    "Cosa pensi ti manchi per padroneggiare completamente questa competenza?"
                ];
                $hint['azione'] = "Approfondire le lacune con esercizi pratici mirati. Verificare se la percezione deriva da esperienze pregresse non applicabili al contesto attuale.";
                $hints['critici'][] = $hint;
                
            } elseif ($gapValue > $sogliaAllineamento) {
                // SOPRAVVALUTAZIONE MODERATA
                $hint['tipo'] = 'sopravvalutazione';
                $hint['icona'] = '⚠️';
                $hint['colore'] = '#fd7e14';
                $hint['bg'] = '#fff3cd';
                $hint['messaggio'] = "Leggera sopravvalutazione. Lo studente si valuta più alto rispetto ai risultati.";
                $hint['domande'] = [
                    "Cosa ti ha portato a valutarti a questo livello per \"{$compItalianName}\"?",
                    "In quali contesti hai già applicato questa competenza?",
                    "Quali aspetti di questa competenza vorresti approfondire?"
                ];
                $hint['azione'] = "Consolidare con esercizi pratici. Fornire feedback costruttivo sui punti di miglioramento.";
                $hints['attenzione'][] = $hint;
                
            } elseif ($gapValue < -$sogliaAllineamento) {
                // SOTTOVALUTAZIONE
                $hint['tipo'] = 'sottovalutazione';
                $hint['icona'] = '💪';
                $hint['colore'] = '#17a2b8';
                $hint['bg'] = '#d1ecf1';
                $hint['messaggio'] = "Lo studente si sottovaluta! I risultati sono migliori della sua percezione.";
                $hint['domande'] = [
                    "Hai notato che nei quiz hai ottenuto risultati migliori di quanto ti aspettassi per \"{$compItalianName}\"?",
                    "Cosa ti frena dal sentirti più sicuro su questa competenza?",
                    "Quali successi hai già raggiunto che potresti non riconoscere?"
                ];
                $hint['azione'] = "Valorizzare i risultati positivi. Rafforzare la fiducia evidenziando i successi concreti.";
                $hints['positivi'][] = $hint;
                
            } else {
                // ALLINEATO
                $hint['tipo'] = 'allineato';
                $hint['icona'] = '✅';
                $hint['colore'] = '#28a745';
                $hint['bg'] = '#d4edda';
                $hint['messaggio'] = "Ottimo allineamento tra autopercezione e risultati effettivi.";
                $hint['domande'] = [];
                $hint['azione'] = "Mantenere il percorso attuale. Eventualmente proporre sfide di livello superiore.";
                $hints['positivi'][] = $hint;
            }
            
        } else {
            // DATI INCOMPLETI
            if ($autoPerc === null && $perfPerc !== null) {
                // Solo performance, no autovalutazione
                $hint['tipo'] = 'no_autovalutazione';
                $hint['icona'] = '📝';
                $hint['colore'] = '#6c757d';
                $hint['bg'] = '#e9ecef';
                
                if ($perfPerc < 40) {
                    $hint['messaggio'] = "Performance bassa senza autovalutazione. Importante capire la percezione dello studente.";
                    $hint['domande'] = [
                        "Come ti senti riguardo a \"{$compItalianName}\"?",
                        "Hai già avuto esperienze con questo argomento prima del corso?",
                        "Quali difficoltà stai incontrando?"
                    ];
                    $hint['azione'] = "Richiedere autovalutazione e approfondire le difficoltà durante il colloquio.";
                    $hints['attenzione'][] = $hint;
                } else {
                    $hint['messaggio'] = "Performance buona, richiedere autovalutazione per confronto completo.";
                    $hint['domande'] = [];
                    $hint['azione'] = "Richiedere autovalutazione per completare l'analisi.";
                    $hints['esplorativi'][] = $hint;
                }
                
            } elseif ($autoPerc !== null && $perfPerc === null) {
                // Solo autovalutazione, no performance
                $hint['tipo'] = 'no_performance';
                $hint['icona'] = '📊';
                $hint['colore'] = '#6c757d';
                $hint['bg'] = '#e9ecef';
                $hint['messaggio'] = "Autovalutazione presente ma nessun quiz completato su questa competenza.";
                $hint['domande'] = [
                    "Raccontami la tua esperienza con \"{$compItalianName}\".",
                    "Dove hai sviluppato questa competenza?",
                    "Quali attività pratiche hai svolto in questo ambito?"
                ];
                $hint['azione'] = "Verificare la competenza con quiz o esercizi pratici.";
                $hints['esplorativi'][] = $hint;
                
            } else {
                // Nessun dato - non aggiungere agli hints
                continue;
            }
        }
    }
    
    return $hints;
}

/**
 * Raggruppa i gap per area
 */
function group_gaps_by_area($gaps, $areaNames) {
    $grouped = [];
    
    foreach ($gaps as $competencyid => $gap) {
        $compCode = $gap->competency_idnumber ?: "ID_{$competencyid}";
        $areaCode = extract_area_from_code($compCode);
        
        if (!isset($grouped[$areaCode])) {
            $areaInfo = $areaNames[$areaCode] ?? $areaNames['OTHER'];
            $grouped[$areaCode] = [
                'code' => $areaCode,
                'name' => $areaInfo['name'],
                'icon' => $areaInfo['icon'],
                'color' => $areaInfo['color'],
                'competencies' => []
            ];
        }
        
        $grouped[$areaCode]['competencies'][$competencyid] = $gap;
    }
    
    // Ordina per nome area
    uasort($grouped, fn($a, $b) => strcmp($a['name'], $b['name']));
    
    return $grouped;
}

/**
 * Ottiene il colore di sfondo per il gap
 */
function get_gap_background($gapValue, $sogliaAllineamento, $sogliaCritico) {
    if ($gapValue === null) return '#f8f9fa';
    
    if ($gapValue > $sogliaCritico) return '#f8d7da';
    if ($gapValue > $sogliaAllineamento) return '#fff3cd';
    if ($gapValue < -$sogliaAllineamento) return '#d1ecf1';
    return '#d4edda';
}

/**
 * Ottiene il badge per il tipo di gap
 */
function get_gap_badge($gapValue, $sogliaAllineamento, $sogliaCritico) {
    if ($gapValue === null) {
        return '<span class="badge badge-secondary">Dati insufficienti</span>';
    }
    
    if ($gapValue > $sogliaCritico) {
        return '<span class="badge badge-danger">🔴 Sopravvalutazione Critica</span>';
    }
    if ($gapValue > $sogliaAllineamento) {
        return '<span class="badge badge-warning">⚠️ Sopravvalutazione</span>';
    }
    if ($gapValue < -$sogliaAllineamento) {
        return '<span class="badge badge-info">💪 Sottovalutazione</span>';
    }
    return '<span class="badge badge-success">✅ Allineato</span>';
}


// ============================================
// REPORT SINGOLO STUDENTE
// ============================================
if ($studentid) {
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    
    // Genera report completo
    $report = assessment_manager::generate_colloquio_report($studentid, null, $courseid);
    
    // Genera hints migliorati con nomi italiani e giustificazioni
    $enhancedHints = generate_enhanced_hints(
        $report->gaps, 
        $sogliaAllineamento, 
        $sogliaCritico, 
        $bloomlevels, 
        $areaNames,
        $studentid,
        $courseid
    );
    
    // Raggruppa per area
    $groupedGaps = group_gaps_by_area($report->gaps, $areaNames);
    
    // Conta per statistiche
    $countCritici = count($enhancedHints['critici']);
    $countAttenzione = count($enhancedHints['attenzione']);
    $countPositivi = count($enhancedHints['positivi']);
    $countAllineati = count(array_filter($enhancedHints['positivi'], fn($h) => ($h['tipo'] ?? '') === 'allineato'));
    $countSottovalutati = count(array_filter($enhancedHints['positivi'], fn($h) => ($h['tipo'] ?? '') === 'sottovalutazione'));
    
    ?>
    <div class="student-report">
        
        <!-- HEADER REPORT -->
        <div class="report-header card">
            <div class="card-body">
                <div class="student-info">
                    <h2>📊 Report Colloquio - <?php echo fullname($student); ?></h2>
                    <p class="text-muted mb-0">
                        Generato il <?php echo userdate($report->generated, '%d/%m/%Y %H:%M'); ?>
                        <?php if ($courseid): ?>
                        | Corso ID: <?php echo $courseid; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="report-actions">
                    <button onclick="window.print();" class="btn btn-primary">🖨️ Stampa</button>
                    <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', [
                        'userid' => $studentid,
                        'courseid' => $courseid ?: '',
                        'show_dual_radar' => 1,
                        'show_gap' => 1,
                        'show_spunti' => 1,
                        'soglia_allineamento' => $sogliaAllineamento,
                        'soglia_critico' => $sogliaCritico
                    ]); ?>" class="btn btn-success" target="_blank">
                        📈 Report CompetencyManager
                    </a>
                    <a href="<?php echo $PAGE->url; ?>" class="btn btn-outline">← Torna ai report</a>
                </div>
            </div>
        </div>
        
        <?php 
        // Verifica se la tabella per le giustificazioni è disponibile
        $qcTableAvailable = get_question_competency_table();
        if (!$qcTableAvailable): 
        ?>
        <div class="alert alert-info" style="border-left: 4px solid #17a2b8; margin-bottom: 20px;">
            <strong>ℹ️ Nota:</strong> La funzione "Dettaglio risposte quiz" non è disponibile. 
            Per abilitarla, è necessario installare il plugin che collega le domande alle competenze 
            (es. <code>local_competencymanager</code> o <code>qbank_competencies</code>).
        </div>
        <?php endif; ?>
        
        <!-- PANNELLO CONFIGURAZIONE SOGLIE -->
        <div class="config-panel card">
            <div class="card-header" style="background: linear-gradient(135deg, #6c757d, #495057); color: white;">
                <h3 class="mb-0">⚙️ Configurazione Soglie Gap Analysis</h3>
            </div>
            <div class="card-body">
                <form method="get" class="soglie-form">
                    <input type="hidden" name="studentid" value="<?php echo $studentid; ?>">
                    <?php if ($courseid): ?>
                    <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                    <?php endif; ?>
                    
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label for="soglia_allineamento"><strong>Soglia Allineamento</strong></label>
                            <p class="small text-muted mb-1">Gap ≤ questa % = ✅ Allineato</p>
                            <div class="input-group">
                                <input type="number" class="form-control" id="soglia_allineamento" 
                                       name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>"
                                       min="5" max="40" step="5">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="soglia_critico"><strong>Soglia Gap Critico</strong></label>
                            <p class="small text-muted mb-1">Gap > questa % = 🔴 Critico</p>
                            <div class="input-group">
                                <input type="number" class="form-control" id="soglia_critico" 
                                       name="soglia_critico" value="<?php echo $sogliaCritico; ?>"
                                       min="20" max="60" step="5">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-block">🔄 Ricalcola Report</button>
                        </div>
                    </div>
                    
                    <div class="soglie-legenda mt-3">
                        <small>
                            <strong>Interpretazione attuale:</strong>
                            <span class="badge badge-success">✅ Allineato</span> Gap ≤ <?php echo $sogliaAllineamento; ?>% |
                            <span class="badge badge-warning">⚠️ Attenzione</span> Gap <?php echo $sogliaAllineamento; ?>-<?php echo $sogliaCritico; ?>% |
                            <span class="badge badge-danger">🔴 Critico</span> Gap > <?php echo $sogliaCritico; ?>%
                        </small>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- PANNELLO SELEZIONE AREE -->
        <?php
        // Calcola quali aree hanno dati per questo studente
        $areasWithData = [];
        foreach ($groupedGaps as $areaCode => $areaData) {
            $areasWithData[$areaCode] = [
                'code' => $areaCode,
                'name' => $areaData['name'],
                'icon' => $areaData['icon'],
                'color' => $areaData['color'],
                'count' => count($areaData['competencies']),
                'selected' => $showAllAreas || in_array($areaCode, $selectedAreas)
            ];
        }
        ?>
        <div class="area-selector card">
            <div class="card-header" style="background: linear-gradient(135deg, #11998e, #38ef7d); color: white; cursor: pointer;"
                 onclick="toggleAreaSelector()">
                <h3 class="mb-0">
                    🎯 Filtra Aree da Visualizzare
                    <span class="float-right" id="areaToggleIcon">▼</span>
                </h3>
            </div>
            <div class="card-body" id="areaSelectorBody" style="display: none;">
                <form method="get" id="areaFilterForm">
                    <input type="hidden" name="studentid" value="<?php echo $studentid; ?>">
                    <?php if ($courseid): ?>
                    <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>">
                    <input type="hidden" name="soglia_critico" value="<?php echo $sogliaCritico; ?>">
                    <input type="hidden" name="aree" id="selectedAreasInput" value="<?php echo htmlspecialchars($selectedAreasParam); ?>">
                    
                    <p class="text-muted mb-3">
                        <strong>Seleziona le aree da includere nel report.</strong> 
                        Le aree non selezionate verranno nascoste dalla visualizzazione.
                    </p>
                    
                    <div class="area-quick-actions mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllAreas()">
                            ✅ Seleziona Tutte
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllAreas()">
                            ⬜ Deseleziona Tutte
                        </button>
                        <span class="ml-3 text-muted" id="areaCountInfo">
                            Selezionate: <strong id="selectedAreaCount"><?php echo $showAllAreas ? count($areasWithData) : count($selectedAreas); ?></strong> 
                            di <?php echo count($areasWithData); ?>
                        </span>
                    </div>
                    
                    <div class="areas-checkbox-grid">
                        <?php foreach ($areasWithData as $areaCode => $area): ?>
                        <label class="area-checkbox-item" style="border-left: 4px solid <?php echo $area['color']; ?>;">
                            <input type="checkbox" 
                                   class="area-checkbox" 
                                   value="<?php echo $areaCode; ?>"
                                   <?php echo $area['selected'] ? 'checked' : ''; ?>
                                   onchange="updateSelectedAreas()">
                            <span class="area-checkbox-label">
                                <span class="area-icon"><?php echo $area['icon']; ?></span>
                                <span class="area-name"><?php echo $area['name']; ?></span>
                                <span class="area-count badge badge-secondary"><?php echo $area['count']; ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <button type="submit" class="btn btn-success btn-lg">
                            🔄 Applica Filtro Aree
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function toggleAreaSelector() {
            var body = document.getElementById('areaSelectorBody');
            var icon = document.getElementById('areaToggleIcon');
            if (body.style.display === 'none') {
                body.style.display = 'block';
                icon.textContent = '▲';
            } else {
                body.style.display = 'none';
                icon.textContent = '▼';
            }
        }
        
        function selectAllAreas() {
            document.querySelectorAll('.area-checkbox').forEach(function(cb) {
                cb.checked = true;
            });
            updateSelectedAreas();
        }
        
        function deselectAllAreas() {
            document.querySelectorAll('.area-checkbox').forEach(function(cb) {
                cb.checked = false;
            });
            updateSelectedAreas();
        }
        
        function updateSelectedAreas() {
            var selected = [];
            document.querySelectorAll('.area-checkbox:checked').forEach(function(cb) {
                selected.push(cb.value);
            });
            document.getElementById('selectedAreasInput').value = selected.join(',');
            document.getElementById('selectedAreaCount').textContent = selected.length;
        }
        </script>
        
        <style>
        .area-selector { margin-bottom: 20px; }
        .areas-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }
        .area-checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0;
        }
        .area-checkbox-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .area-checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
        }
        .area-checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .area-icon { font-size: 1.2em; }
        .area-name { flex: 1; font-weight: 500; }
        .area-count { font-size: 0.85em; }
        .area-quick-actions { border-bottom: 1px solid #dee2e6; padding-bottom: 15px; }
        </style>
        <div class="areas-overview card">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <h3 class="mb-0">📁 Riepilogo per Area 
                    <?php if (!$showAllAreas): ?>
                    <small class="ml-2" style="opacity: 0.8;">(Filtrate: <?php echo count($selectedAreas); ?> aree)</small>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="areas-grid">
                    <?php foreach ($groupedGaps as $areaCode => $area): 
                        // FILTRO AREE: skip se non selezionata
                        if (!$showAllAreas && !in_array($areaCode, $selectedAreas)) continue;
                        
                        $areaCount = count($area['competencies']);
                    ?>
                    <a href="#area-<?php echo $areaCode; ?>" style="text-decoration: none; color: inherit;"><div class="area-box" style="border-left: 4px solid <?php echo $area['color']; ?>;">
                        <span class="area-icon"><?php echo $area['icon']; ?></span>
                        <div class="area-info">
                            <strong><?php echo $area['name']; ?></strong>
                            <small><?php echo $areaCount; ?> competenze</small>
                        </div>
                    </div></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- STATISTICHE RIEPILOGATIVE -->
        <div class="stats-row">
            <div class="stat-box">
                <span class="stat-number"><?php echo $report->stats->total_competencies; ?></span>
                <span class="stat-label">Competenze Totali</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?php echo $report->stats->with_self_assessment; ?></span>
                <span class="stat-label">Con Autovalutazione</span>
            </div>
            <div class="stat-box green">
                <span class="stat-number"><?php echo $countAllineati; ?></span>
                <span class="stat-label">✅ Allineati</span>
            </div>
            <div class="stat-box orange">
                <span class="stat-number"><?php echo $countCritici + $countAttenzione; ?></span>
                <span class="stat-label">⚠️🔴 Sopravvalutazione</span>
            </div>
            <div class="stat-box blue">
                <span class="stat-number"><?php echo $countSottovalutati; ?></span>
                <span class="stat-label">💪 Sottovalutazione</span>
            </div>
        </div>
        
        <!-- SEZIONE SPUNTI COLLOQUIO ARRICCHITI -->
        <?php if (!empty($enhancedHints['critici']) || !empty($enhancedHints['attenzione'])): ?>
        <div class="hints-section card">
            <div class="card-header" style="background: linear-gradient(135deg, #dc3545, #fd7e14); color: white;">
                <h3 class="mb-0">🎯 Priorità per il Colloquio</h3>
            </div>
            <div class="card-body">
                
                <?php if (!empty($enhancedHints['critici'])): 
                    // Filtra hints in base alle aree selezionate
                    $filteredCritici = array_filter($enhancedHints['critici'], function($hint) use ($showAllAreas, $selectedAreas) {
                        return $showAllAreas || in_array($hint['area_code'] ?? 'OTHER', $selectedAreas);
                    });
                ?>
                <?php if (!empty($filteredCritici)): ?>
                <div class="hints-group mb-4">
                    <h4 class="text-danger">🔴 Gap Critici - Priorità Alta</h4>
                    <?php foreach ($filteredCritici as $hint): ?>
                    <div class="hint-card critical">
                        <div class="hint-header">
                            <div>
                                <span class="area-badge" style="background: <?php echo $hint['area']['color']; ?>;">
                                    <?php echo $hint['area']['icon'] . ' ' . $hint['area']['name']; ?>
                                </span>
                                <strong class="hint-title"><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                                <span class="hint-code"><?php echo htmlspecialchars($hint['codice']); ?></span>
                            </div>
                            <div class="hint-values">
                                <span class="value-auto">🧑 Auto: <?php echo $hint['autovalutazione'] !== null ? $hint['autovalutazione'] . '%' : '-'; ?></span>
                                <span class="value-perf">📊 Reale: <?php echo $hint['performance'] !== null ? $hint['performance'] . '%' : '-'; ?></span>
                                <?php if ($hint['gap'] !== null): ?>
                                <span class="value-gap gap-negative">Gap: <?php echo ($hint['gap'] > 0 ? '+' : '') . $hint['gap']; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="hint-body">
                            <p class="hint-message"><?php echo $hint['messaggio']; ?></p>
                            
                            <!-- GIUSTIFICAZIONE: Perché questo valore? -->
                            <?php if (!empty($hint['justification']['table_missing'])): ?>
                            <div class="justification-box" style="background: #f8f9fa; border-color: #6c757d;">
                                <small class="text-muted">ℹ️ Dettaglio risposte non disponibile. Tabella collegamento domande-competenze non trovata.</small>
                            </div>
                            <?php elseif (!empty($hint['justification']['quizzes'])): ?>
                            <div class="justification-box">
                                <h5>📋 Perché questo valore? (<?php echo $hint['justification']['correct_questions']; ?>/<?php echo $hint['justification']['total_questions']; ?> corrette)</h5>
                                
                                <?php foreach ($hint['justification']['quizzes'] as $quiz): ?>
                                <div class="quiz-detail">
                                    <div class="quiz-header">
                                        <strong>📝 <?php echo htmlspecialchars($quiz['name']); ?></strong>
                                        <span class="quiz-score"><?php echo $quiz['correct']; ?>/<?php echo $quiz['total']; ?> corrette</span>
                                        <a href="<?php echo $quiz['attempt_url']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            🔍 Vedi tentativo
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($quiz['wrong'])): ?>
                                    <div class="wrong-questions">
                                        <small class="text-danger"><strong>❌ Domande sbagliate:</strong></small>
                                        <?php foreach (array_slice($quiz['wrong'], 0, 3) as $wrong): ?>
                                        <div class="wrong-item">
                                            <span class="wrong-text"><?php echo htmlspecialchars(substr($wrong['text'], 0, 100)); ?>...</span>
                                            <?php if ($wrong['student_answer']): ?>
                                            <span class="wrong-answer">Risposta: "<?php echo htmlspecialchars(substr($wrong['student_answer'], 0, 50)); ?>"</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($quiz['wrong']) > 3): ?>
                                        <small class="text-muted">...e altre <?php echo count($quiz['wrong']) - 3; ?> domande</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="hint-questions">
                                <h5>💬 Domande suggerite per il colloquio:</h5>
                                <ul>
                                    <?php foreach ($hint['domande'] as $domanda): ?>
                                    <li><em>"<?php echo $domanda; ?>"</em></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="hint-action">
                                <strong>📋 Azione consigliata:</strong> <?php echo $hint['azione']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($enhancedHints['attenzione'])): 
                    // Filtra hints in base alle aree selezionate
                    $filteredAttenzione = array_filter($enhancedHints['attenzione'], function($hint) use ($showAllAreas, $selectedAreas) {
                        return $showAllAreas || in_array($hint['area_code'] ?? 'OTHER', $selectedAreas);
                    });
                ?>
                <?php if (!empty($filteredAttenzione)): ?>
                <div class="hints-group mb-4">
                    <h4 class="text-warning">⚠️ Gap Moderati - Attenzione</h4>
                    <?php foreach ($filteredAttenzione as $hint): ?>
                    <div class="hint-card warning">
                        <div class="hint-header">
                            <div>
                                <span class="area-badge" style="background: <?php echo $hint['area']['color']; ?>;">
                                    <?php echo $hint['area']['icon'] . ' ' . $hint['area']['name']; ?>
                                </span>
                                <strong class="hint-title"><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                                <span class="hint-code"><?php echo htmlspecialchars($hint['codice']); ?></span>
                            </div>
                            <div class="hint-values">
                                <span class="value-auto">🧑 Auto: <?php echo $hint['autovalutazione'] !== null ? $hint['autovalutazione'] . '%' : '-'; ?></span>
                                <span class="value-perf">📊 Reale: <?php echo $hint['performance'] !== null ? $hint['performance'] . '%' : '-'; ?></span>
                                <?php if ($hint['gap'] !== null): ?>
                                <span class="value-gap gap-warning">Gap: <?php echo ($hint['gap'] > 0 ? '+' : '') . $hint['gap']; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="hint-body">
                            <p class="hint-message"><?php echo $hint['messaggio']; ?></p>
                            
                            <!-- GIUSTIFICAZIONE -->
                            <?php if (!empty($hint['justification']['table_missing'])): ?>
                            <!-- Tabella non disponibile - skip silenzioso -->
                            <?php elseif (!empty($hint['justification']['quizzes'])): ?>
                            <div class="justification-box">
                                <h5>📋 Dettaglio risposte (<?php echo $hint['justification']['correct_questions']; ?>/<?php echo $hint['justification']['total_questions']; ?> corrette)</h5>
                                
                                <?php foreach ($hint['justification']['quizzes'] as $quiz): ?>
                                <div class="quiz-detail">
                                    <div class="quiz-header">
                                        <strong>📝 <?php echo htmlspecialchars($quiz['name']); ?></strong>
                                        <span class="quiz-score"><?php echo $quiz['correct']; ?>/<?php echo $quiz['total']; ?></span>
                                        <a href="<?php echo $quiz['attempt_url']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            🔍 Vedi
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($quiz['wrong'])): ?>
                                    <div class="wrong-questions">
                                        <?php foreach (array_slice($quiz['wrong'], 0, 2) as $wrong): ?>
                                        <div class="wrong-item">
                                            <span class="wrong-text">❌ <?php echo htmlspecialchars(substr($wrong['text'], 0, 80)); ?>...</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($hint['domande'])): ?>
                            <div class="hint-questions">
                                <h5>💬 Domande suggerite:</h5>
                                <ul>
                                    <?php foreach ($hint['domande'] as $domanda): ?>
                                    <li><em>"<?php echo $domanda; ?>"</em></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <div class="hint-action">
                                <strong>📋 Azione:</strong> <?php echo $hint['azione']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SEZIONE PUNTI DI FORZA -->
        <?php if (!empty($enhancedHints['positivi'])): 
            // Filtra hints in base alle aree selezionate
            $filteredPositivi = array_filter($enhancedHints['positivi'], function($hint) use ($showAllAreas, $selectedAreas) {
                return $showAllAreas || in_array($hint['area_code'] ?? 'OTHER', $selectedAreas);
            });
        ?>
        <?php if (!empty($filteredPositivi)): ?>
        <div class="hints-section card">
            <div class="card-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <h3 class="mb-0">💪 Punti di Forza e Allineamenti</h3>
            </div>
            <div class="card-body">
                <div class="positivi-grid">
                    <?php foreach ($filteredPositivi as $hint): ?>
                    <div class="positivo-card" style="background: <?php echo $hint['bg']; ?>; border-left: 4px solid <?php echo $hint['colore']; ?>;">
                        <div class="positivo-header">
                            <span class="positivo-icon"><?php echo $hint['icona']; ?></span>
                            <div>
                                <span class="area-badge-sm" style="background: <?php echo $hint['area']['color']; ?>;">
                                    <?php echo $hint['area']['icon']; ?>
                                </span>
                                <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($hint['codice']); ?></small>
                            </div>
                        </div>
                        <div class="positivo-values">
                            <?php if ($hint['autovalutazione'] !== null): ?>
                            <span>🧑 <?php echo $hint['autovalutazione']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($hint['performance'] !== null): ?>
                            <span>📊 <?php echo $hint['performance']; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <p class="positivo-message mb-0"><?php echo $hint['messaggio']; ?></p>
                        
                        <?php if (!empty($hint['domande'])): ?>
                        <div class="positivo-questions mt-2">
                            <?php foreach ($hint['domande'] as $domanda): ?>
                            <small class="d-block"><em>💡 "<?php echo $domanda; ?>"</em></small>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- TABELLA DETTAGLIO PER AREA -->
        <?php foreach ($groupedGaps as $areaCode => $area): 
            // FILTRO AREE: skip se non selezionata
            if (!$showAllAreas && !in_array($areaCode, $selectedAreas)) continue;
        ?>
        <div class="area-detail card" id="area-<?php echo $areaCode; ?>">
            <div class="card-header" style="background: <?php echo $area['color']; ?>; color: white;">
                <h3 class="mb-0"><?php echo $area['icon'] . ' ' . $area['name']; ?> (<?php echo count($area['competencies']); ?> competenze)</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr style="background: #2c3e50; color: white;">
                                <th style="min-width: 250px;">Competenza</th>
                                <th class="text-center" style="width: 100px;">🧑 Auto</th>
                                <th class="text-center" style="width: 100px;">📊 Reale</th>
                                <th class="text-center" style="width: 80px;">Gap</th>
                                <th class="text-center" style="width: 160px;">Analisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($area['competencies'] as $competencyid => $gap): 
                                $compCode = $gap->competency_idnumber ?: "ID_{$competencyid}";
                                $compItalianName = get_competency_italian_name($gap->competencyid, $gap->competency_idnumber);
                                if (!$compItalianName) {
                                    $compItalianName = $gap->competency_name ?: $compCode;
                                }
                                
                                $autoPerc = isset($gap->self_assessment) && $gap->self_assessment ? 
                                            round(($gap->self_assessment / 6) * 100) : null;
                                $perfPerc = isset($gap->real_performance_percentage) ? 
                                            round($gap->real_performance_percentage) : 
                                            (isset($gap->real_performance) && $gap->real_performance ? 
                                             round(($gap->real_performance / 6) * 100) : null);
                                
                                $gapValue = ($autoPerc !== null && $perfPerc !== null) ? $autoPerc - $perfPerc : null;
                                $bgColor = get_gap_background($gapValue, $sogliaAllineamento, $sogliaCritico);
                                
                                $autoBloom = isset($gap->self_assessment) && $gap->self_assessment ? 
                                             $bloomlevels[$gap->self_assessment] : '-';
                                $perfBloom = isset($gap->real_performance) && $gap->real_performance ? 
                                             $bloomlevels[$gap->real_performance] : '-';
                            ?>
                            <tr style="background: <?php echo $bgColor; ?>;">
                                <td>
                                    <strong><?php echo htmlspecialchars($compItalianName); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($compCode); ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($autoPerc !== null): ?>
                                    <div class="value-display">
                                        <span class="value-perc"><?php echo $autoPerc; ?>%</span>
                                        <span class="bloom-badge level-<?php echo $gap->self_assessment; ?>">
                                            <?php echo $autoBloom; ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($perfPerc !== null): ?>
                                    <div class="value-display">
                                        <span class="value-perc"><?php echo $perfPerc; ?>%</span>
                                        <span class="bloom-badge level-<?php echo $gap->real_performance ?? 0; ?>">
                                            <?php echo $perfBloom; ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($gapValue !== null): ?>
                                    <strong style="color: <?php 
                                        echo $gapValue > $sogliaAllineamento ? '#dc3545' : 
                                             ($gapValue < -$sogliaAllineamento ? '#17a2b8' : '#28a745'); 
                                    ?>; font-size: 14px;">
                                        <?php echo ($gapValue > 0 ? '+' : '') . $gapValue; ?>%
                                    </strong>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php echo get_gap_badge($gapValue, $sogliaAllineamento, $sogliaCritico); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- BOX NOTE COACH -->
        <div class="coach-notes card">
            <div class="card-header" style="background: linear-gradient(135deg, #6f42c1, #e83e8c); color: white;">
                <h3 class="mb-0">📝 Note del Coach per il Colloquio</h3>
            </div>
            <div class="card-body">
                <textarea class="form-control" rows="6" placeholder="Scrivi qui le tue osservazioni, note e punti da discutere durante il colloquio con lo studente..."></textarea>
                <div class="mt-3">
                    <button class="btn btn-outline-primary" onclick="window.print();">🖨️ Stampa Report con Note</button>
                </div>
            </div>
        </div>
        
        <!-- LINK RAPIDO A COMPETENCYMANAGER -->
        <div class="quick-links card">
            <div class="card-body text-center">
                <h4>🔗 Collegamenti Rapidi</h4>
                <div class="links-row">
                    <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', [
                        'userid' => $studentid,
                        'courseid' => $courseid ?: ''
                    ]); ?>" class="btn btn-lg btn-primary" target="_blank">
                        📊 Report Competenze Completo
                    </a>
                    <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', [
                        'userid' => $studentid,
                        'courseid' => $courseid ?: '',
                        'show_dual_radar' => 1,
                        'show_gap' => 1,
                        'show_spunti' => 1,
                        'soglia_allineamento' => $sogliaAllineamento,
                        'soglia_critico' => $sogliaCritico
                    ]); ?>" class="btn btn-lg btn-success" target="_blank">
                        🎯 Report con Doppio Radar
                    </a>
                    <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', [
                        'userid' => $studentid,
                        'courseid' => $courseid ?: '',
                        'print' => 1,
                        'print_panoramica' => 1,
                        'print_dual_radar' => 1,
                        'print_gap' => 1,
                        'print_spunti' => 1,
                        'soglia_allineamento' => $sogliaAllineamento,
                        'soglia_critico' => $sogliaCritico
                    ]); ?>" class="btn btn-lg btn-info" target="_blank">
                        🖨️ Versione Stampabile
                    </a>
                </div>
            </div>
        </div>
        
    </div>
    <?php
    
} else {
    // ============================================
    // OVERVIEW: LISTA STUDENTI
    // ============================================
    $students = coach_manager::get_coach_students($userid, $courseid);
    
    ?>
    <div class="reports-overview">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <h3 class="mb-0">📊 Genera Report Colloquio</h3>
            </div>
            <div class="card-body">
                <p>Seleziona uno studente per generare il report con gap analysis e spunti per il colloquio:</p>
                
                <?php if (empty($students)): ?>
                    <div class="alert alert-info">
                        <p class="mb-0">Nessuno studente assegnato ai tuoi corsi.</p>
                    </div>
                <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($students as $student): 
                        $assesscount = $DB->count_records('local_coachmanager_self_assess', ['studentid' => $student->id]);
                        $quizcount = isset($student->quiz_stats) ? $student->quiz_stats->completed_quizzes : 0;
                    ?>
                    <div class="student-card">
                        <div class="student-avatar">👤</div>
                        <div class="student-info">
                            <h4><?php echo fullname($student); ?></h4>
                            <small class="text-muted"><?php echo $student->email; ?></small>
                        </div>
                        <div class="student-stats">
                            <span class="stat" title="Autovalutazioni completate">📝 <?php echo $assesscount; ?> autovalutazioni</span>
                            <?php if ($quizcount > 0): ?>
                            <span class="stat" title="Quiz completati">✅ <?php echo $quizcount; ?> quiz</span>
                            <?php endif; ?>
                        </div>
                        <div class="student-actions">
                            <a href="?studentid=<?php echo $student->id; ?>&amp;courseid=<?php echo $courseid; ?>" class="btn btn-primary">
                                📊 Genera Report
                            </a>
                            <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', [
                                'userid' => $student->id
                            ]); ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                                📈 CompetencyManager
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<style>
/* ============================================
   STILI REPORT COLLOQUIO - VERSIONE COMPLETA
   ============================================ */

.student-report, .reports-overview {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.report-header .card-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.report-header h2 {
    margin: 0 0 5px 0;
    color: #2c3e50;
}

.report-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Config Panel */
.config-panel {
    margin-bottom: 20px;
}

.soglie-form .row {
    display: flex;
    align-items: flex-end;
    gap: 20px;
}

.soglie-form .col-md-4 {
    flex: 1;
}

.soglie-legenda {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

/* Areas Overview */
.areas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.area-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.area-icon {
    font-size: 24px;
}

.area-info strong {
    display: block;
    font-size: 14px;
}

.area-info small {
    color: #666;
}

.area-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    color: white;
    margin-right: 8px;
}

.area-badge-sm {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 10px;
    color: white;
    margin-right: 5px;
}

/* Stats Row */
.stats-row {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.stat-box {
    flex: 1;
    min-width: 140px;
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-top: 4px solid #6c757d;
}

.stat-box.green { border-top-color: #28a745; }
.stat-box.orange { border-top-color: #fd7e14; }
.stat-box.blue { border-top-color: #17a2b8; }

.stat-number {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
}

.stat-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
    border: none;
}

.card-header {
    padding: 18px 20px;
    border-bottom: none;
}

.card-header h3 { 
    margin: 0; 
    font-size: 18px;
    font-weight: 600;
}

.card-body { padding: 20px; }

/* Hints Section */
.hints-group h4 {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid currentColor;
}

.hint-card {
    background: white;
    border-radius: 10px;
    margin-bottom: 15px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.hint-card.critical {
    border-left: 5px solid #dc3545;
}

.hint-card.warning {
    border-left: 5px solid #fd7e14;
}

.hint-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px 20px;
    background: #f8f9fa;
    flex-wrap: wrap;
    gap: 10px;
}

.hint-title {
    font-size: 16px;
    color: #2c3e50;
    display: block;
    margin-top: 5px;
}

.hint-code {
    display: block;
    font-size: 11px;
    color: #6c757d;
    font-family: monospace;
    margin-top: 2px;
}

.hint-values {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.hint-values span {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.value-auto { background: #e3f2fd; color: #1565c0; }
.value-perf { background: #e8f5e9; color: #2e7d32; }
.value-gap { background: #ffebee; color: #c62828; }
.gap-negative { background: #ffebee; }
.gap-warning { background: #fff8e1; color: #f57f17; }

.hint-body {
    padding: 20px;
}

.hint-message {
    font-size: 14px;
    color: #495057;
    margin-bottom: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #6c757d;
}

/* Justification Box */
.justification-box {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.justification-box h5 {
    font-size: 14px;
    color: #856404;
    margin-bottom: 12px;
}

.quiz-detail {
    background: white;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
}

.quiz-header {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.quiz-score {
    background: #e9ecef;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
}

.wrong-questions {
    padding-top: 10px;
    border-top: 1px dashed #ddd;
}

.wrong-item {
    padding: 8px;
    background: #fff5f5;
    border-radius: 4px;
    margin-top: 6px;
    font-size: 12px;
}

.wrong-text {
    display: block;
    color: #721c24;
}

.wrong-answer {
    display: block;
    color: #856404;
    font-style: italic;
    margin-top: 4px;
}

.hint-questions {
    margin-bottom: 15px;
}

.hint-questions h5 {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 10px;
}

.hint-questions ul {
    margin: 0;
    padding-left: 20px;
}

.hint-questions li {
    margin-bottom: 8px;
    color: #0d6efd;
}

.hint-action {
    padding: 12px;
    background: #e3f2fd;
    border-radius: 6px;
    font-size: 13px;
}

/* Positivi Grid */
.positivi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.positivo-card {
    padding: 15px;
    border-radius: 8px;
}

.positivo-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.positivo-icon {
    font-size: 24px;
}

.positivo-values {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 500;
}

.positivo-message {
    font-size: 12px;
    color: #495057;
}

.positivo-questions {
    padding-top: 10px;
    border-top: 1px dashed rgba(0,0,0,0.1);
}

/* Area Detail Tables */
.area-detail {
    margin-bottom: 20px;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.value-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

.value-perc {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

.bloom-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 500;
}

.bloom-badge.level-0 { background: #f8f9fa; color: #6c757d; }
.bloom-badge.level-1 { background: #e9ecef; color: #495057; }
.bloom-badge.level-2 { background: #cce5ff; color: #004085; }
.bloom-badge.level-3 { background: #d4edda; color: #155724; }
.bloom-badge.level-4 { background: #fff3cd; color: #856404; }
.bloom-badge.level-5 { background: #f8d7da; color: #721c24; }
.bloom-badge.level-6 { background: #d1c4e9; color: #4a148c; }

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success { background: #28a745; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.badge-danger { background: #dc3545; color: white; }
.badge-info { background: #17a2b8; color: white; }
.badge-secondary { background: #6c757d; color: white; }

/* Coach Notes */
.coach-notes textarea {
    font-size: 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
}

.coach-notes textarea:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
}

/* Quick Links */
.quick-links {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.links-row {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 15px;
}

/* Students Grid (Overview) */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 15px;
}

.student-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    transition: all 0.2s;
}

.student-card:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.student-avatar { 
    font-size: 36px; 
    background: white;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.student-info { flex: 1; }
.student-info h4 { margin: 0 0 3px 0; font-size: 15px; color: #2c3e50; }

.student-stats {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.student-stats .stat { 
    font-size: 12px; 
    color: #666;
    background: white;
    padding: 4px 8px;
    border-radius: 4px;
}

.student-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-decoration: none;
}

.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-lg { padding: 14px 28px; font-size: 16px; }
.btn-block { width: 100%; }

.btn-primary { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: white; }
.btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.btn-info { background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white; }
.btn-outline { background: white; border: 2px solid #0d6efd; color: #0d6efd; }
.btn-outline-primary { background: white; border: 2px solid #0d6efd; color: #0d6efd; }
.btn-outline-secondary { background: white; border: 1px solid #6c757d; color: #6c757d; }

/* Form Controls */
.form-control {
    padding: 10px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
    width: 100%;
}

.form-control:focus {
    border-color: #0d6efd;
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
}

.input-group {
    display: flex;
}

.input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-group-append .input-group-text {
    padding: 10px 14px;
    background: #e9ecef;
    border: 2px solid #e9ecef;
    border-left: none;
    border-radius: 0 8px 8px 0;
}

/* Utilities */
.text-muted { color: #6c757d !important; }
.text-danger { color: #dc3545 !important; }
.text-warning { color: #fd7e14 !important; }
.text-success { color: #28a745 !important; }
.text-info { color: #17a2b8 !important; }

.mb-0 { margin-bottom: 0 !important; }
.mb-1 { margin-bottom: 5px !important; }
.mb-2 { margin-bottom: 10px !important; }
.mb-3 { margin-bottom: 15px !important; }
.mb-4 { margin-bottom: 20px !important; }
.mt-2 { margin-top: 10px !important; }
.mt-3 { margin-top: 15px !important; }

.d-block { display: block !important; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

/* Print Styles */
@media print {
    .report-actions, 
    .config-panel,
    .quick-links,
    .nav-tabs,
    .btn:not(.btn-print) { 
        display: none !important; 
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    
    .student-report {
        max-width: 100%;
    }
    
    .hint-card, .area-detail {
        page-break-inside: avoid;
    }
    
    .coach-notes textarea {
        border: 1px solid #333;
        min-height: 150px;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
    }
    
    .stat-box {
        min-width: auto;
    }
    
    .report-header .card-body {
        flex-direction: column;
        text-align: center;
    }
    
    .hint-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .hint-values {
        flex-wrap: wrap;
    }
    
    .students-grid, .positivi-grid, .areas-grid {
        grid-template-columns: 1fr;
    }
    
    .student-card {
        flex-wrap: wrap;
    }
    
    .links-row {
        flex-direction: column;
    }
    
    .links-row .btn {
        width: 100%;
    }
    
    .soglie-form .row {
        flex-direction: column;
    }
}
</style>

<?php
echo $OUTPUT->footer();
