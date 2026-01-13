<?php
/**
 * Report Generator - VERSIONE 7 (DINAMICA)
 * Con supporto per:
 * - Lettura descrizioni competenze/aree dal DATABASE
 * - Report per singolo quiz
 * - Filtro per area
 * - Confronto tra quiz nel tempo
 * - Supporto multi-settore (MECCANICA, AUTOMOBILE, LOGISTICA, ecc.)
 */

namespace local_competencyreport;

defined('MOODLE_INTERNAL') || die();

class report_generator {
    
    // Cache per le descrizioni (evita query ripetute)
    private static $areaDescriptionsCache = [];
    private static $competencyDescriptionsCache = [];
    
    // Icone di default per le aree (fallback)
    private static $defaultAreaIcons = [
        'DT' => '📐', 'MIS' => '📏', 'LMB' => '🔧', 'LMC' => '⚙️',
        'CNC' => '🖥️', 'ASS' => '🔩', 'GEN' => '🏭', 'PIAN' => '📋',
        'MAN' => '🔧', 'AUT' => '🤖', 'SAQ' => '🛡️', 'CSP' => '👥', 'PRG' => '📊',
        'MR' => '🔧', 'MAu' => '🚗', 'HV' => '⚡', 'ADAS' => '📡',
        'DEFAULT' => '📁'
    ];
    
    // Colori di default per le aree
    private static $defaultAreaColors = [
        'DT' => '#e74c3c', 'MIS' => '#3498db', 'LMB' => '#2ecc71', 'LMC' => '#f39c12',
        'CNC' => '#9b59b6', 'ASS' => '#1abc9c', 'GEN' => '#e67e22', 'PIAN' => '#34495e',
        'MAN' => '#16a085', 'AUT' => '#8e44ad', 'SAQ' => '#c0392b', 'CSP' => '#27ae60', 'PRG' => '#2980b9',
        'MR' => '#3498db', 'MAu' => '#e74c3c', 'HV' => '#f1c40f', 'ADAS' => '#9b59b6',
        'DEFAULT' => '#95a5a6'
    ];

    /**
     * NUOVO: Ottieni le descrizioni delle AREE dal framework nel database
     * Legge le competenze di 2° livello (es. "06-01" = "Lavorazioni meccaniche di base")
     * 
     * AUTOMOBILE: area = lettera dalla terza parte (AUTOMOBILE_MR_A1 → area A)
     * MECCANICA: area = seconda parte (MECCANICA_LMB_01 → area LMB)
     * 
     * @param string $sector Settore (es. MECCANICA, AUTOMOBILE)
     * @param int $courseid ID corso per trovare il framework associato
     * @return array Descrizioni aree ['MIS' => ['name' => 'Metrologia e Misure', 'icon' => '📏', 'color' => '#3498db'], ...]
     */
    public static function get_area_descriptions_from_framework($sector = null, $courseid = null) {
        global $DB;
        
        // Controlla cache
        $cacheKey = ($sector ?: 'all') . '_' . ($courseid ?: '0');
        if (isset(self::$areaDescriptionsCache[$cacheKey])) {
            return self::$areaDescriptionsCache[$cacheKey];
        }
        
        $areaDescriptions = [];
        
        if ($sector) {
            // Trova tutte le competenze del settore - usa get_recordset per evitare problemi di duplicati
            $sql = "SELECT c.id, c.idnumber, c.shortname, c.description
                    FROM {competency} c
                    WHERE c.idnumber LIKE :sector
                    ORDER BY c.idnumber";
            
            $recordset = $DB->get_recordset_sql($sql, ['sector' => $sector . '_%']);
            
            // Raggruppa per area
            $areaGroups = [];
            foreach ($recordset as $comp) {
                $parts = explode('_', $comp->idnumber);
                
                // Determina il codice area in base al settore
                if ($sector == 'AUTOMOBILE') {
                    // AUTOMOBILE_MR_A1 → area = A (prima lettera della terza parte)
                    if (count($parts) >= 3) {
                        $thirdPart = $parts[2];
                        preg_match('/^([A-Z])/i', $thirdPart, $matches);
                        $areaCode = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER';
                    } else {
                        $areaCode = 'OTHER';
                    }
                } else {
                    // MECCANICA, ELETTRICITA, ecc.: area = seconda parte
                    // MECCANICA_LMB_01 → area = LMB
                    $areaCode = isset($parts[1]) ? $parts[1] : 'OTHER';
                }
                
                if (!isset($areaGroups[$areaCode])) {
                    $areaGroups[$areaCode] = [];
                }
                $areaGroups[$areaCode][] = $comp;
            }
            $recordset->close();
            
            // Per ogni area, costruisci le descrizioni
            foreach ($areaGroups as $areaCode => $comps) {
                // Estrai nome descrittivo usando la mappatura o il fallback
                $areaName = self::extractAreaName($areaCode, null, $comps, $sector);
                
                // Icone e colori specifici per AUTOMOBILE
                if ($sector == 'AUTOMOBILE') {
                    $icons = [
                        'A' => '📋', 'B' => '🔧', 'C' => '💧', 'D' => '💨',
                        'E' => '⚙️', 'F' => '🛞', 'G' => '🔌', 'H' => '📡',
                        'I' => '❄️', 'J' => '⚡', 'K' => '🚗', 'L' => '🛡️',
                        'M' => '👥', 'N' => '📅',
                    ];
                    $colors = [
                        'A' => '#3498db', 'B' => '#e74c3c', 'C' => '#1abc9c', 'D' => '#95a5a6',
                        'E' => '#f39c12', 'F' => '#9b59b6', 'G' => '#2ecc71', 'H' => '#e67e22',
                        'I' => '#00bcd4', 'J' => '#ffc107', 'K' => '#795548', 'L' => '#607d8b',
                        'M' => '#8bc34a', 'N' => '#ff5722',
                    ];
                    $icon = $icons[$areaCode] ?? '📁';
                    $color = $colors[$areaCode] ?? '#95a5a6';
                } else {
                    $icon = self::$defaultAreaIcons[$areaCode] ?? self::$defaultAreaIcons['DEFAULT'];
                    $color = self::$defaultAreaColors[$areaCode] ?? self::$defaultAreaColors['DEFAULT'];
                }
                
                $areaDescriptions[$areaCode] = [
                    'name' => $areaName,
                    'icon' => $icon,
                    'color' => $color
                ];
            }
        }
        
        // Se non abbiamo trovato nulla, usa il metodo alternativo
        if (empty($areaDescriptions)) {
            $areaDescriptions = self::getAreaDescriptionsFallback($sector);
        }
        
        // Salva in cache
        self::$areaDescriptionsCache[$cacheKey] = $areaDescriptions;
        
        return $areaDescriptions;
    }
    
    /**
     * NUOVO: Ottieni le descrizioni delle COMPETENZE dal framework nel database
     * 
     * @param string $sector Settore (es. MECCANICA, AUTOMOBILE)
     * @return array Descrizioni competenze ['MECCANICA_MIS_01' => ['name' => 'Utilizza strumenti...', 'area' => 'MIS'], ...]
     */
    public static function get_competency_descriptions_from_framework($sector = null) {
        global $DB;
        
        // Controlla cache
        $cacheKey = $sector ?: 'all';
        if (isset(self::$competencyDescriptionsCache[$cacheKey])) {
            return self::$competencyDescriptionsCache[$cacheKey];
        }
        
        $competencyDescriptions = [];
        
        $params = [];
        $where = "c.idnumber IS NOT NULL AND c.idnumber != ''";
        
        if ($sector) {
            $where .= " AND c.idnumber LIKE :sector";
            $params['sector'] = $sector . '_%';
        }
        
        $sql = "SELECT c.id, c.idnumber, c.shortname, c.description
                FROM {competency} c
                WHERE {$where}
                ORDER BY c.idnumber";
        
        // Usa recordset invece di get_records_sql per evitare problemi di chiave duplicata
        $recordset = $DB->get_recordset_sql($sql, $params);
        
        foreach ($recordset as $comp) {
            // Estrai area dal codice
            $parts = explode('_', $comp->idnumber);
            
            // Determina il codice area in base al settore
            if ($sector == 'AUTOMOBILE') {
                // AUTOMOBILE_MR_A1 → area = A
                if (count($parts) >= 3) {
                    $thirdPart = $parts[2];
                    preg_match('/^([A-Z])/i', $thirdPart, $matches);
                    $areaCode = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER';
                } else {
                    $areaCode = 'OTHER';
                }
            } else {
                // MECCANICA_LMB_01 → area = LMB
                $areaCode = count($parts) >= 2 ? $parts[1] : '';
            }
            
            // Pulisci la descrizione (rimuovi HTML)
            $cleanDescription = strip_tags($comp->description);
            $cleanDescription = html_entity_decode($cleanDescription, ENT_QUOTES, 'UTF-8');
            $cleanDescription = trim($cleanDescription);
            
            // Se la descrizione è vuota, usa shortname
            if (empty($cleanDescription)) {
                $cleanDescription = $comp->shortname;
            }
            
            // Abbrevia se troppo lunga per le etichette
            $shortName = $cleanDescription;
            if (strlen($shortName) > 50) {
                $shortName = substr($shortName, 0, 47) . '...';
            }
            
            $competencyDescriptions[$comp->idnumber] = [
                'name' => $shortName,
                'full_name' => $cleanDescription,
                'area' => $areaCode,
                'description' => $cleanDescription
            ];
        }
        $recordset->close();
        
        // Salva in cache
        self::$competencyDescriptionsCache[$cacheKey] = $competencyDescriptions;
        
        return $competencyDescriptions;
    }
    
    /**
     * Helper: Estrai nome descrittivo dell'area
     */
    private static function extractAreaName($areaCode, $areaParent, $competencies, $sector = '') {
        // Mappatura per AUTOMOBILE (A-N)
        $automobileMapping = [
            'A' => 'Accoglienza, diagnosi & documentazione',
            'B' => 'Motore & alimentazione',
            'C' => 'Lubrificazione & raffreddamento',
            'D' => 'Scarico & controllo emissioni',
            'E' => 'Trasmissione & trazione',
            'F' => 'Sospensioni, sterzo & freni',
            'G' => 'Elettronica di bordo & reti CAN/LIN',
            'H' => 'Sistemi ADAS & sensori',
            'I' => 'Climatizzazione & HVAC',
            'J' => 'Veicoli ibridi & elettrici (HV)',
            'K' => 'Carrozzeria & allestimenti',
            'L' => 'Qualità, sicurezza & ambiente',
            'M' => 'Relazione cliente & amministrazione',
            'N' => 'Manutenzione programmata & collaudi',
        ];
        
        // Mappatura per MECCANICA e altri settori
        $manualMapping = [
            'DT' => 'Disegno Tecnico',
            'MIS' => 'Metrologia e Misure',
            'LMB' => 'Lavorazioni Base',
            'LMC' => 'Macchine Convenzionali',
            'CNC' => 'CNC e Programmazione',
            'ASS' => 'Assemblaggio',
            'GEN' => 'Processi Generali',
            'PIAN' => 'Pianificazione',
            'MAN' => 'Manutenzione',
            'AUT' => 'Automazione',
            'SAQ' => 'Sicurezza e Qualità',
            'CSP' => 'Collaborazione',
            'PRG' => 'Progettazione',
            'MR' => 'Meccatronico Riparatore',
            'MAu' => 'Meccatronico Automobile',
            'HV' => 'Alta Tensione',
            'ADAS' => 'Sistemi ADAS',
        ];
        
        // Prima prova dal parent
        if ($areaParent && !empty($areaParent->description)) {
            $desc = strip_tags($areaParent->description);
            $desc = html_entity_decode($desc, ENT_QUOTES, 'UTF-8');
            $desc = trim($desc);
            if (!empty($desc) && strlen($desc) < 100) {
                return $desc;
            }
        }
        
        // Per AUTOMOBILE usa la mappatura specifica
        if ($sector == 'AUTOMOBILE' && isset($automobileMapping[$areaCode])) {
            return $automobileMapping[$areaCode];
        }
        
        // Poi prova mappatura manuale
        if (isset($manualMapping[$areaCode])) {
            return $manualMapping[$areaCode];
        }
        
        // Infine usa il codice stesso
        return $areaCode;
    }
    
    /**
     * Fallback per descrizioni aree se il DB non le ha
     */
    private static function getAreaDescriptionsFallback($sector) {
        $defaults = [
            'MECCANICA' => [
                'DT' => ['name' => 'Disegno Tecnico', 'icon' => '📐', 'color' => '#e74c3c'],
                'MIS' => ['name' => 'Metrologia e Misure', 'icon' => '📏', 'color' => '#3498db'],
                'LMB' => ['name' => 'Lavorazioni Base', 'icon' => '🔧', 'color' => '#2ecc71'],
                'LMC' => ['name' => 'Macchine Convenzionali', 'icon' => '⚙️', 'color' => '#f39c12'],
                'CNC' => ['name' => 'CNC e Programmazione', 'icon' => '🖥️', 'color' => '#9b59b6'],
                'ASS' => ['name' => 'Assemblaggio', 'icon' => '🔩', 'color' => '#1abc9c'],
                'GEN' => ['name' => 'Processi Generali', 'icon' => '🏭', 'color' => '#e67e22'],
                'PIAN' => ['name' => 'Pianificazione', 'icon' => '📋', 'color' => '#34495e'],
                'MAN' => ['name' => 'Manutenzione', 'icon' => '🔧', 'color' => '#16a085'],
                'AUT' => ['name' => 'Automazione', 'icon' => '🤖', 'color' => '#8e44ad'],
                'SAQ' => ['name' => 'Sicurezza e Qualità', 'icon' => '🛡️', 'color' => '#c0392b'],
                'CSP' => ['name' => 'Collaborazione', 'icon' => '👥', 'color' => '#27ae60'],
                'PRG' => ['name' => 'Progettazione', 'icon' => '📊', 'color' => '#2980b9'],
            ],
            'AUTOMOBILE' => [
                'A' => ['name' => 'Accoglienza, diagnosi & documentazione', 'icon' => '📋', 'color' => '#3498db'],
                'B' => ['name' => 'Motore & alimentazione', 'icon' => '🔧', 'color' => '#e74c3c'],
                'C' => ['name' => 'Lubrificazione & raffreddamento', 'icon' => '💧', 'color' => '#1abc9c'],
                'D' => ['name' => 'Scarico & controllo emissioni', 'icon' => '💨', 'color' => '#95a5a6'],
                'E' => ['name' => 'Trasmissione & trazione', 'icon' => '⚙️', 'color' => '#f39c12'],
                'F' => ['name' => 'Sospensioni, sterzo & freni', 'icon' => '🛞', 'color' => '#9b59b6'],
                'G' => ['name' => 'Elettronica di bordo & reti CAN/LIN', 'icon' => '🔌', 'color' => '#2ecc71'],
                'H' => ['name' => 'Sistemi ADAS & sensori', 'icon' => '📡', 'color' => '#e67e22'],
                'I' => ['name' => 'Climatizzazione & HVAC', 'icon' => '❄️', 'color' => '#00bcd4'],
                'J' => ['name' => 'Veicoli ibridi & elettrici (HV)', 'icon' => '⚡', 'color' => '#ffc107'],
                'K' => ['name' => 'Carrozzeria & allestimenti', 'icon' => '🚗', 'color' => '#795548'],
                'L' => ['name' => 'Qualità, sicurezza & ambiente', 'icon' => '🛡️', 'color' => '#607d8b'],
                'M' => ['name' => 'Relazione cliente & amministrazione', 'icon' => '👥', 'color' => '#8bc34a'],
                'N' => ['name' => 'Manutenzione programmata & collaudi', 'icon' => '📅', 'color' => '#ff5722'],
            ],
        ];
        
        return $defaults[$sector] ?? [];
    }
    
    /**
     * NUOVO: Rileva il settore dalle competenze dello studente
     */
    public static function detect_sector_from_competencies($competencies) {
        if (empty($competencies)) {
            return null;
        }
        
        // Prendi la prima competenza e estrai il settore
        $first = reset($competencies);
        $idnumber = $first['idnumber'] ?? '';
        
        if (empty($idnumber)) {
            return null;
        }
        
        $parts = explode('_', $idnumber);
        return $parts[0] ?? null;
    }
    
    /**
     * Ottieni tutti i tentativi di quiz di uno studente con competenze
     */
    public static function get_student_quiz_attempts($userid, $courseid = null) {
        global $DB;
        
        $params = ['userid' => $userid];
        $coursecondition = '';
        
        if ($courseid) {
            $coursecondition = 'AND q.course = :courseid';
            $params['courseid'] = $courseid;
        }
        
        $sql = "SELECT DISTINCT 
                    qa.id as attemptid,
                    qa.quiz,
                    qa.userid,
                    qa.attempt,
                    qa.state,
                    qa.timefinish,
                    qa.sumgrades,
                    q.name as quizname,
                    q.course,
                    q.grade as maxgrade,
                    c.fullname as coursename
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                JOIN {course} c ON c.id = q.course
                WHERE qa.userid = :userid
                AND qa.state = 'finished'
                {$coursecondition}
                ORDER BY qa.timefinish DESC";
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Ottieni lista quiz disponibili per uno studente in un corso
     */
    public static function get_available_quizzes($userid, $courseid) {
        global $DB;
        
        $sql = "SELECT DISTINCT 
                    q.id,
                    q.name,
                    COUNT(DISTINCT qa.id) as attempts,
                    MAX(qa.timefinish) as last_attempt
                FROM {quiz} q
                JOIN {quiz_attempts} qa ON qa.quiz = q.id
                WHERE qa.userid = :userid
                AND q.course = :courseid
                AND qa.state = 'finished'
                GROUP BY q.id, q.name
                ORDER BY q.name";
        
        return $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
    }
    
    /**
     * Ottieni le aree (prefissi) delle competenze disponibili
     */
    public static function get_available_areas($userid, $courseid = null) {
        global $DB;
        
        $attempts = self::get_student_quiz_attempts($userid, $courseid);
        $areas = [];
        
        foreach ($attempts as $attempt) {
            $questions = self::get_attempt_question_results($attempt->attemptid);
            
            foreach ($questions as $question) {
                if (!empty($question->competency_idnumber)) {
                    $parts = explode('_', $question->competency_idnumber);
                    if (count($parts) >= 1) {
                        $area = $parts[0];
                        if (!isset($areas[$area])) {
                            $areas[$area] = [
                                'code' => $area,
                                'name' => $area,
                                'count' => 0
                            ];
                        }
                        $areas[$area]['count']++;
                    }
                }
            }
        }
        
        return $areas;
    }
    
    /**
     * Ottieni i risultati per domanda di un tentativo
     */
    public static function get_attempt_question_results($attemptid) {
        global $DB;
        
        $sql = "SELECT 
                    qat.id as attemptquestionid,
                    qu.id as questionusageid,
                    qat.questionid,
                    q.name as questionname,
                    qat.slot,
                    qat.maxmark,
                    COALESCE(
                        (SELECT MAX(fraction) 
                         FROM {question_attempt_steps} qas2 
                         WHERE qas2.questionattemptid = qat.id 
                         AND qas2.fraction IS NOT NULL), 
                        0
                    ) as fraction,
                    qcbq.competencyid,
                    qcbq.difficultylevel,
                    comp.idnumber as competency_idnumber,
                    comp.shortname as competency_name,
                    comp.description as competency_description
                FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qat ON qat.questionusageid = qu.id
                JOIN {question} q ON q.id = qat.questionid
                LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                WHERE qa.id = :attemptid
                GROUP BY qat.id, qu.id, qat.questionid, q.name, qat.slot, qat.maxmark,
                         qcbq.competencyid, qcbq.difficultylevel, comp.idnumber, comp.shortname, comp.description
                ORDER BY qat.slot";
        
        return $DB->get_records_sql($sql, ['attemptid' => $attemptid]);
    }
    
    /**
     * Calcola il punteggio per competenza di uno studente
     */
    public static function get_student_competency_scores($userid, $courseid = null, $quizids = null, $area = null) {
        global $DB;
        
        $attempts = self::get_student_quiz_attempts($userid, $courseid);
        
        if ($quizids) {
            if (!is_array($quizids)) {
                $quizids = [$quizids];
            }
            $attempts = array_filter($attempts, function($a) use ($quizids) {
                return in_array($a->quiz, $quizids);
            });
        }
        
        $competencies = [];
        
        foreach ($attempts as $attempt) {
            $questions = self::get_attempt_question_results($attempt->attemptid);
            
            foreach ($questions as $question) {
                if (empty($question->competencyid)) {
                    continue;
                }
                
                if ($area && !empty($question->competency_idnumber)) {
                    $parts = explode('_', $question->competency_idnumber);
                    if ($parts[0] !== $area) {
                        continue;
                    }
                }
                
                $compid = $question->competencyid;
                
                if (!isset($competencies[$compid])) {
                    $compArea = '';
                    if (!empty($question->competency_idnumber)) {
                        $parts = explode('_', $question->competency_idnumber);
                        $compArea = $parts[1] ?? $parts[0];
                    }
                    
                    // Pulisci la descrizione
                    $cleanDesc = strip_tags($question->competency_description ?? '');
                    $cleanDesc = html_entity_decode($cleanDesc, ENT_QUOTES, 'UTF-8');
                    $cleanDesc = trim($cleanDesc);
                    
                    $competencies[$compid] = [
                        'id' => $compid,
                        'idnumber' => $question->competency_idnumber,
                        'name' => $question->competency_name,
                        'description' => $cleanDesc, // NUOVO: descrizione pulita
                        'area' => $compArea,
                        'total_questions' => 0,
                        'correct_questions' => 0,
                        'total_score' => 0,
                        'max_score' => 0,
                        'by_level' => [
                            1 => ['total' => 0, 'correct' => 0, 'score' => 0, 'max' => 0],
                            2 => ['total' => 0, 'correct' => 0, 'score' => 0, 'max' => 0],
                            3 => ['total' => 0, 'correct' => 0, 'score' => 0, 'max' => 0],
                        ]
                    ];
                }
                
                $level = $question->difficultylevel ?: 1;
                $score = $question->fraction * $question->maxmark;
                $maxscore = $question->maxmark;
                $iscorrect = ($question->fraction >= 0.5) ? 1 : 0;
                
                $competencies[$compid]['total_questions']++;
                $competencies[$compid]['correct_questions'] += $iscorrect;
                $competencies[$compid]['total_score'] += $score;
                $competencies[$compid]['max_score'] += $maxscore;
                
                $competencies[$compid]['by_level'][$level]['total']++;
                $competencies[$compid]['by_level'][$level]['correct'] += $iscorrect;
                $competencies[$compid]['by_level'][$level]['score'] += $score;
                $competencies[$compid]['by_level'][$level]['max'] += $maxscore;
            }
        }
        
        foreach ($competencies as &$comp) {
            $comp['percentage'] = $comp['max_score'] > 0 
                ? round(($comp['total_score'] / $comp['max_score']) * 100, 1) 
                : 0;
            
            foreach ($comp['by_level'] as $level => &$leveldata) {
                $leveldata['percentage'] = $leveldata['max'] > 0 
                    ? round(($leveldata['score'] / $leveldata['max']) * 100, 1) 
                    : 0;
            }
        }
        
        return $competencies;
    }
    
    /**
     * Ottieni dati per grafico radar (con filtri)
     */
    public static function get_radar_chart_data($userid, $courseid = null, $quizids = null, $area = null) {
        $competencies = self::get_student_competency_scores($userid, $courseid, $quizids, $area);
        
        $labels = [];
        $data = [];
        $colors = [];
        
        $colorPalette = [
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 99, 132, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(199, 199, 199, 0.7)',
            'rgba(83, 102, 255, 0.7)',
        ];
        
        $i = 0;
        foreach ($competencies as $comp) {
            $label = $comp['idnumber'] ?: $comp['name'];
            if (strlen($label) > 15) {
                $label = substr($label, 0, 12) . '...';
            }
            $labels[] = $label;
            $data[] = $comp['percentage'];
            $colors[] = $colorPalette[$i % count($colorPalette)];
            $i++;
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
            'competencies' => array_values($competencies)
        ];
    }
    
    /**
     * Ottieni statistiche riepilogative studente
     */
    public static function get_student_summary($userid, $courseid = null, $quizids = null, $area = null) {
        $competencies = self::get_student_competency_scores($userid, $courseid, $quizids, $area);
        
        $summary = [
            'total_competencies' => count($competencies),
            'total_questions' => 0,
            'correct_questions' => 0,
            'overall_percentage' => 0,
            'by_level' => [
                1 => ['name' => 'Base ⭐', 'total' => 0, 'correct' => 0, 'percentage' => 0],
                2 => ['name' => 'Intermedio ⭐⭐', 'total' => 0, 'correct' => 0, 'percentage' => 0],
                3 => ['name' => 'Avanzato ⭐⭐⭐', 'total' => 0, 'correct' => 0, 'percentage' => 0],
            ],
            'by_area' => [],
            'strengths' => [],
            'weaknesses' => [],
        ];
        
        foreach ($competencies as $comp) {
            $summary['total_questions'] += $comp['total_questions'];
            $summary['correct_questions'] += $comp['correct_questions'];
            
            foreach ($comp['by_level'] as $level => $leveldata) {
                $summary['by_level'][$level]['total'] += $leveldata['total'];
                $summary['by_level'][$level]['correct'] += $leveldata['correct'];
            }
            
            $areaCode = $comp['area'] ?: 'Altro';
            if (!isset($summary['by_area'][$areaCode])) {
                $summary['by_area'][$areaCode] = [
                    'name' => $areaCode,
                    'total' => 0,
                    'correct' => 0,
                    'percentage' => 0
                ];
            }
            $summary['by_area'][$areaCode]['total'] += $comp['total_questions'];
            $summary['by_area'][$areaCode]['correct'] += $comp['correct_questions'];
            
            if ($comp['percentage'] >= 80) {
                $summary['strengths'][] = $comp;
            } elseif ($comp['percentage'] < 50) {
                $summary['weaknesses'][] = $comp;
            }
        }
        
        $summary['overall_percentage'] = $summary['total_questions'] > 0
            ? round(($summary['correct_questions'] / $summary['total_questions']) * 100, 1)
            : 0;
        
        foreach ($summary['by_level'] as $level => &$leveldata) {
            $leveldata['percentage'] = $leveldata['total'] > 0
                ? round(($leveldata['correct'] / $leveldata['total']) * 100, 1)
                : 0;
        }
        
        foreach ($summary['by_area'] as &$areadata) {
            $areadata['percentage'] = $areadata['total'] > 0
                ? round(($areadata['correct'] / $areadata['total']) * 100, 1)
                : 0;
        }
        
        usort($summary['strengths'], fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        usort($summary['weaknesses'], fn($a, $b) => $a['percentage'] <=> $b['percentage']);
        
        $summary['correct_total'] = $summary['correct_questions'];
        $summary['questions_total'] = $summary['total_questions'];
        
        return $summary;
    }
    
    /**
     * Ottieni confronto tra quiz nel tempo
     */
    public static function get_quiz_comparison($userid, $courseid) {
        global $DB;
        
        $attempts = self::get_student_quiz_attempts($userid, $courseid);
        
        $quizResults = [];
        
        foreach ($attempts as $attempt) {
            $quizid = $attempt->quiz;
            
            if (!isset($quizResults[$quizid])) {
                $quizResults[$quizid] = [
                    'id' => $quizid,
                    'name' => $attempt->quizname,
                    'attempts' => []
                ];
            }
            
            $questions = self::get_attempt_question_results($attempt->attemptid);
            $totalScore = 0;
            $maxScore = 0;
            $competencyCount = 0;
            $correctCount = 0;
            $totalCount = 0;
            
            foreach ($questions as $q) {
                if (!empty($q->competencyid)) {
                    $competencyCount++;
                    $totalScore += $q->fraction * $q->maxmark;
                    $maxScore += $q->maxmark;
                    $totalCount++;
                    if ($q->fraction >= 0.5) $correctCount++;
                }
            }
            
            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
            
            $quizResults[$quizid]['attempts'][] = [
                'attemptid' => $attempt->attemptid,
                'attempt_number' => $attempt->attempt,
                'timefinish' => $attempt->timefinish,
                'date' => date('d/m/Y H:i', $attempt->timefinish),
                'percentage' => $percentage,
                'correct' => $correctCount,
                'total' => $totalCount,
                'competencies' => $competencyCount
            ];
        }
        
        foreach ($quizResults as &$quiz) {
            usort($quiz['attempts'], fn($a, $b) => $a['timefinish'] <=> $b['timefinish']);
        }
        
        return $quizResults;
    }
    
    /**
     * Ottieni progressi nel tempo
     */
    public static function get_progress_over_time($userid, $courseid = null) {
        $attempts = self::get_student_quiz_attempts($userid, $courseid);
        
        usort($attempts, fn($a, $b) => $a->timefinish <=> $b->timefinish);
        
        $progress = [];
        
        foreach ($attempts as $attempt) {
            $questions = self::get_attempt_question_results($attempt->attemptid);
            $totalScore = 0;
            $maxScore = 0;
            
            foreach ($questions as $q) {
                if (!empty($q->competencyid)) {
                    $totalScore += $q->fraction * $q->maxmark;
                    $maxScore += $q->maxmark;
                }
            }
            
            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
            
            $progress[] = [
                'date' => date('d/m/Y', $attempt->timefinish),
                'datetime' => $attempt->timefinish,
                'quiz' => $attempt->quizname,
                'percentage' => $percentage
            ];
        }
        
        return $progress;
    }
    
    /**
     * Report per singolo quiz
     */
    public static function get_single_quiz_report($userid, $quizid) {
        global $DB;
        
        $sql = "SELECT qa.id as attemptid, qa.*, q.name as quizname
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                WHERE qa.userid = :userid
                AND qa.quiz = :quizid
                AND qa.state = 'finished'
                ORDER BY qa.timefinish DESC
                LIMIT 1";
        
        $attempt = $DB->get_record_sql($sql, ['userid' => $userid, 'quizid' => $quizid]);
        
        if (!$attempt) {
            return null;
        }
        
        $questions = self::get_attempt_question_results($attempt->attemptid);
        
        $report = [
            'quiz_id' => $quizid,
            'quiz_name' => $attempt->quizname,
            'attempt_id' => $attempt->attemptid,
            'date' => date('d/m/Y H:i', $attempt->timefinish),
            'questions' => [],
            'competencies' => [],
            'summary' => [
                'total' => 0,
                'correct' => 0,
                'percentage' => 0
            ]
        ];
        
        foreach ($questions as $q) {
            $isCorrect = $q->fraction >= 0.5;
            
            $report['questions'][] = [
                'name' => $q->questionname,
                'competency' => $q->competency_idnumber ?: 'N/A',
                'level' => $q->difficultylevel ?: 1,
                'correct' => $isCorrect,
                'score' => round($q->fraction * 100, 1)
            ];
            
            if (!empty($q->competencyid)) {
                $report['summary']['total']++;
                if ($isCorrect) $report['summary']['correct']++;
                
                $compid = $q->competencyid;
                if (!isset($report['competencies'][$compid])) {
                    $report['competencies'][$compid] = [
                        'id' => $compid,
                        'idnumber' => $q->competency_idnumber,
                        'name' => $q->competency_name,
                        'total' => 0,
                        'correct' => 0,
                        'percentage' => 0
                    ];
                }
                $report['competencies'][$compid]['total']++;
                if ($isCorrect) $report['competencies'][$compid]['correct']++;
            }
        }
        
        $report['summary']['percentage'] = $report['summary']['total'] > 0
            ? round(($report['summary']['correct'] / $report['summary']['total']) * 100, 1)
            : 0;
        
        foreach ($report['competencies'] as &$comp) {
            $comp['percentage'] = $comp['total'] > 0
                ? round(($comp['correct'] / $comp['total']) * 100, 1)
                : 0;
        }
        
        return $report;
    }
    
    /**
     * Esporta dati in formato CSV
     */
    public static function export_student_csv($userid, $courseid = null, $quizid = null, $area = null) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $userid]);
        $competencies = self::get_student_competency_scores($userid, $courseid, $quizid, $area);
        
        $csv = [];
        
        $csv[] = [
            'Studente', 'Email', 'Area', 'Competenza_Codice', 'Competenza_Nome',
            'Domande_Totali', 'Domande_Corrette', 'Percentuale',
            'Domande_Base', 'Corrette_Base', 'Percentuale_Base',
            'Domande_Intermedio', 'Corrette_Intermedio', 'Percentuale_Intermedio',
            'Domande_Avanzato', 'Corrette_Avanzato', 'Percentuale_Avanzato'
        ];
        
        foreach ($competencies as $comp) {
            $csv[] = [
                fullname($user),
                $user->email,
                $comp['area'],
                $comp['idnumber'],
                $comp['name'],
                $comp['total_questions'],
                $comp['correct_questions'],
                $comp['percentage'],
                $comp['by_level'][1]['total'],
                $comp['by_level'][1]['correct'],
                $comp['by_level'][1]['percentage'],
                $comp['by_level'][2]['total'],
                $comp['by_level'][2]['correct'],
                $comp['by_level'][2]['percentage'],
                $comp['by_level'][3]['total'],
                $comp['by_level'][3]['correct'],
                $comp['by_level'][3]['percentage'],
            ];
        }
        
        return $csv;
    }
    
    /**
     * Esporta dati classe in formato CSV
     */
    public static function export_class_csv($courseid, $quizid = null, $area = null) {
        global $DB;
        
        $context = \context_course::instance($courseid);
        $students = get_enrolled_users($context, 'mod/quiz:attempt');
        
        $csv = [];
        
        $csv[] = [
            'Studente', 'Email', 'Area', 'Competenza_Codice', 'Competenza_Nome',
            'Domande_Totali', 'Domande_Corrette', 'Percentuale', 'Livello_Raggiunto'
        ];
        
        foreach ($students as $student) {
            $competencies = self::get_student_competency_scores($student->id, $courseid, $quizid, $area);
            
            foreach ($competencies as $comp) {
                $levelreached = 'Non raggiunto';
                if ($comp['by_level'][3]['percentage'] >= 60) {
                    $levelreached = 'Avanzato';
                } elseif ($comp['by_level'][2]['percentage'] >= 60) {
                    $levelreached = 'Intermedio';
                } elseif ($comp['by_level'][1]['percentage'] >= 60) {
                    $levelreached = 'Base';
                }
                
                $csv[] = [
                    fullname($student),
                    $student->email,
                    $comp['area'],
                    $comp['idnumber'],
                    $comp['name'],
                    $comp['total_questions'],
                    $comp['correct_questions'],
                    $comp['percentage'],
                    $levelreached
                ];
            }
        }
        
        return $csv;
    }
    
    /**
     * Verifica se lo studente può vedere il proprio report
     */
    public static function student_can_view_own_report($userid, $courseid) {
        global $DB;
        
        $auth = $DB->get_record('local_competencyreport_auth', [
            'userid' => $userid,
            'courseid' => $courseid,
            'authorized' => 1
        ]);
        
        return !empty($auth);
    }
    
    /**
     * Autorizza/revoca accesso studente al proprio report
     */
    public static function set_student_authorization($userid, $courseid, $authorized, $authorizedby) {
        global $DB;
        
        $existing = $DB->get_record('local_competencyreport_auth', [
            'userid' => $userid,
            'courseid' => $courseid
        ]);
        
        if ($existing) {
            $existing->authorized = $authorized ? 1 : 0;
            $existing->authorizedby = $authorizedby;
            $existing->timemodified = time();
            $DB->update_record('local_competencyreport_auth', $existing);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->authorized = $authorized ? 1 : 0;
            $record->authorizedby = $authorizedby;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('local_competencyreport_auth', $record);
        }
        
        return true;
    }
}
