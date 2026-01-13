<?php
// ============================================
// AJAX: Confronta due studenti
// ============================================
// File: ajax_compare_students.php
// Ritorna dati per confronto radar e tabella
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_login();

header('Content-Type: application/json');

// Parametri
$student1_id = required_param('student1', PARAM_INT);
$student2_id = required_param('student2', PARAM_INT);

// Verifica permessi
$context = context_system::instance();
if (!has_capability('moodle/site:viewreports', $context)) {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

// Verifica che gli studenti esistano
$student1 = $DB->get_record('user', ['id' => $student1_id], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['id' => $student2_id], '*', MUST_EXIST);

/**
 * Mappa dei prefissi competenze alle aree
 */
function get_area_map() {
    return [
        'MECCANICA_ASS' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12', 'classe' => 'assemblaggio'],
        'MECCANICA_AUT' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c', 'classe' => 'automazione'],
        'MECCANICA_CSP' => ['nome' => 'Collaborazione', 'icona' => '🤝', 'colore' => '#8e44ad', 'classe' => 'collaborazione'],
        'MECCANICA_CNC' => ['nome' => 'CNC', 'icona' => '🖥️', 'colore' => '#00bcd4', 'classe' => 'cnc'],
        'MECCANICA_DIS' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db', 'classe' => 'disegno'],
        'MECCANICA_LAV' => ['nome' => 'Lavorazioni Gen.', 'icona' => '🏭', 'colore' => '#9e9e9e', 'classe' => 'lav-generali'],
        'MECCANICA_LMC' => ['nome' => 'Lav. Macchine', 'icona' => '⚙️', 'colore' => '#607d8b', 'classe' => 'lav-macchine'],
        'MECCANICA_LMB' => ['nome' => 'Lav. Manuali', 'icona' => '🔧', 'colore' => '#795548', 'classe' => 'lav-base'],
        'MECCANICA_MAN' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22', 'classe' => 'manutenzione'],
        'AUTOMOBILE_MAu' => ['nome' => 'Manut. Auto', 'icona' => '🚗', 'colore' => '#3498db', 'classe' => 'manutenzione-auto'],
        'AUTOMOBILE_MR' => ['nome' => 'Manut. Riparaz.', 'icona' => '🔧', 'colore' => '#e74c3c', 'classe' => 'manutenzione-rip'],
        'MECCANICA_MIS' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c', 'classe' => 'misurazione'],
        'MECCANICA_PIA' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6', 'classe' => 'pianificazione'],
        'MECCANICA_PRO' => ['nome' => 'Programmazione', 'icona' => '💻', 'colore' => '#2ecc71', 'classe' => 'programmazione'],
        'MECCANICA_SIC' => ['nome' => 'Sicurezza', 'icona' => '🛡️', 'colore' => '#c0392b', 'classe' => 'sicurezza'],
    ];
}

/**
 * Calcola punteggi per area di uno studente
 */
function get_student_scores($studentid) {
    global $DB;
    
    $area_map = get_area_map();
    $scores = [];
    
    // Inizializza
    foreach ($area_map as $prefix => $info) {
        $scores[$info['classe']] = [
            'nome' => $info['nome'],
            'icona' => $info['icona'],
            'quiz_sum' => 0,
            'quiz_count' => 0,
            'quiz_media' => 0
        ];
    }
    
    // Carica risultati quiz
    $quiz_results = $DB->get_records_sql("
        SELECT 
            qa.questionid,
            qas.fraction
        FROM {quiz_attempts} quiza
        JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
        WHERE quiza.userid = ?
        AND quiza.state = 'finished'
        AND qas.sequencenumber = (
            SELECT MAX(qas2.sequencenumber) 
            FROM {question_attempt_steps} qas2 
            WHERE qas2.questionattemptid = qa.id
        )
    ", [$studentid]);
    
    // Cerca tabella mapping competenze-domande
    $comp_question_table = null;
    $tables = ['qbank_competenciesbyquestion', 'qbank_comp_question', 'local_competencymanager_qcomp'];
    foreach ($tables as $table) {
        if ($DB->get_manager()->table_exists($table)) {
            $comp_question_table = $table;
            break;
        }
    }
    
    if (!$comp_question_table) {
        return $scores; // Ritorna scores vuoti
    }
    
    // Carica mapping
    $question_competencies = [];
    $mappings = $DB->get_records($comp_question_table);
    foreach ($mappings as $map) {
        $qid = $map->questionid;
        $cid = $map->competencyid;
        if (!isset($question_competencies[$qid])) {
            $question_competencies[$qid] = [];
        }
        $question_competencies[$qid][] = $cid;
    }
    
    // Carica competenze
    $competencies = $DB->get_records('competency', [], '', 'id, idnumber');
    
    // Calcola punteggi
    foreach ($quiz_results as $result) {
        if (!isset($question_competencies[$result->questionid])) continue;
        
        foreach ($question_competencies[$result->questionid] as $compid) {
            if (!isset($competencies[$compid])) continue;
            
            $idnumber = $competencies[$compid]->idnumber;
            
            // Trova area
            foreach ($area_map as $prefix => $info) {
                if (strpos($idnumber, $prefix) === 0) {
                    $area_key = $info['classe'];
                    $score = ($result->fraction !== null) ? floatval($result->fraction) * 100 : 0;
                    $scores[$area_key]['quiz_sum'] += $score;
                    $scores[$area_key]['quiz_count']++;
                    break;
                }
            }
        }
    }
    
    // Calcola medie
    foreach ($scores as $key => &$area) {
        if ($area['quiz_count'] > 0) {
            $area['quiz_media'] = round($area['quiz_sum'] / $area['quiz_count'], 1);
        }
    }
    
    return $scores;
}

try {
    // Ottieni punteggi per entrambi gli studenti
    $scores1 = get_student_scores($student1_id);
    $scores2 = get_student_scores($student2_id);
    
    // Prepara dati per la risposta
    $comparison = [];
    $radarLabels = [];
    $radarData1 = [];
    $radarData2 = [];
    
    foreach ($scores1 as $key => $area1) {
        $area2 = $scores2[$key] ?? ['quiz_media' => 0];
        
        $diff = round($area1['quiz_media'] - $area2['quiz_media'], 1);
        
        $comparison[] = [
            'area' => $key,
            'nome' => $area1['nome'],
            'icona' => $area1['icona'],
            'student1' => $area1['quiz_media'],
            'student2' => $area2['quiz_media'],
            'diff' => $diff,
            'diffClass' => $diff > 0 ? 'positive' : ($diff < 0 ? 'negative' : 'neutral')
        ];
        
        $radarLabels[] = $area1['nome'];
        $radarData1[] = $area1['quiz_media'];
        $radarData2[] = $area2['quiz_media'];
    }
    
    // Ordina per differenza (le più grandi prima)
    usort($comparison, fn($a, $b) => abs($b['diff']) <=> abs($a['diff']));
    
    echo json_encode([
        'success' => true,
        'student1' => [
            'id' => $student1_id,
            'name' => fullname($student1)
        ],
        'student2' => [
            'id' => $student2_id,
            'name' => fullname($student2)
        ],
        'comparison' => $comparison,
        'radar' => [
            'labels' => array_slice($radarLabels, 0, 10), // Max 10 per leggibilità
            'data1' => array_slice($radarData1, 0, 10),
            'data2' => array_slice($radarData2, 0, 10)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
