<?php
// ============================================
// CoachManager - Library Functions
// ============================================

defined('MOODLE_INTERNAL') || die();

/**
 * Aggiunge link al menu di navigazione
 */
function local_coachmanager_extend_navigation(global_navigation $navigation) {
    global $CFG, $USER;

    // Verifica se l'utente ha le capabilities
    $context = context_system::instance();
    if (!has_capability('local/coachmanager:view', $context)) {
        return;
    }

    // Aggiungi nodo principale (Dashboard Coach V2)
    $node = $navigation->add(
        get_string('coach_dashboard', 'local_coachmanager'),
        new moodle_url('/local/coachmanager/coach_dashboard_v2.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'coachmanager',
        new pix_icon('i/dashboard', '')
    );

    $node->showinflatnavigation = true;

    // Aggiungi sotto-nodi
    $node->add(
        get_string('my_students', 'local_coachmanager'),
        new moodle_url('/local/coachmanager/coach_dashboard_v2.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'coachmanager_students'
    );

    $node->add(
        get_string('bilancio_competenze', 'local_coachmanager'),
        new moodle_url('/local/coachmanager/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'coachmanager_bilancio'
    );
}

/**
 * Aggiunge link alla navigazione del corso (sidebar dentro un corso)
 */
function local_coachmanager_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/coachmanager:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('coach_dashboard', 'local_coachmanager'),
        new moodle_url('/local/coachmanager/coach_dashboard_v2.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'coachmanager_course',
        new pix_icon('i/dashboard', '')
    );
}

/**
 * Aggiunge link alle impostazioni del sito
 */
function local_coachmanager_extend_settings_navigation($settingsnav, $context) {
    global $CFG, $PAGE;
    
    // Solo nel contesto sistema
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return;
    }
    
    // Verifica capabilities
    if (!has_capability('local/coachmanager:view', $context)) {
        return;
    }
    
    // Trova il nodo reports
    $reportsnode = $settingsnav->find('reports', navigation_node::TYPE_SETTING);
    
    if ($reportsnode) {
        $reportsnode->add(
            get_string('coachmanager', 'local_coachmanager'),
            new moodle_url('/local/coachmanager/index.php'),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * Ritorna la mappa delle aree competenze
 */
function local_coachmanager_get_area_map() {
    return [
        'MECCANICA_ASS' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12', 'settore' => 'meccanica', 'classe' => 'assemblaggio'],
        'MECCANICA_AUT' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c', 'settore' => 'automazione', 'classe' => 'automazione'],
        'MECCANICA_CSP' => ['nome' => 'Collaborazione e Sviluppo Personale', 'icona' => '🤝', 'colore' => '#8e44ad', 'settore' => 'meccanica', 'classe' => 'collaborazione'],
        'MECCANICA_CNC' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4', 'settore' => 'automazione', 'classe' => 'cnc'],
        'MECCANICA_DIS' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db', 'settore' => 'meccanica', 'classe' => 'disegno'],
        'MECCANICA_LAV' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e', 'settore' => 'meccanica', 'classe' => 'lav-generali'],
        'MECCANICA_LMC' => ['nome' => 'Lavorazioni Macchine Convenzionali', 'icona' => '⚙️', 'colore' => '#607d8b', 'settore' => 'meccanica', 'classe' => 'lav-macchine'],
        'MECCANICA_LMB' => ['nome' => 'Lavorazioni Manuali di Base', 'icona' => '🔧', 'colore' => '#795548', 'settore' => 'meccanica', 'classe' => 'lav-base'],
        'MECCANICA_MAN' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22', 'settore' => 'meccanica', 'classe' => 'manutenzione'],
        'AUTOMOBILE_MAu' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db', 'settore' => 'automobile', 'classe' => 'manutenzione-auto'],
        'AUTOMOBILE_MR' => ['nome' => 'Manutenzione e Riparazione', 'icona' => '🔧', 'colore' => '#e74c3c', 'settore' => 'automobile', 'classe' => 'manutenzione-rip'],
        'MECCANICA_MIS' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c', 'settore' => 'meccanica', 'classe' => 'misurazione'],
        'MECCANICA_PIA' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6', 'settore' => 'meccanica', 'classe' => 'pianificazione'],
        'MECCANICA_PRO' => ['nome' => 'Programmazione e Progettazione', 'icona' => '💻', 'colore' => '#2ecc71', 'settore' => 'automazione', 'classe' => 'programmazione'],
        'MECCANICA_SIC' => ['nome' => 'Sicurezza, Ambiente e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b', 'settore' => 'meccanica', 'classe' => 'sicurezza'],
    ];
}

/**
 * Ritorna l'area di una competenza dato il suo idnumber
 */
function local_coachmanager_get_competency_area($idnumber) {
    $area_map = local_coachmanager_get_area_map();
    foreach ($area_map as $prefix => $info) {
        if (strpos($idnumber, $prefix) === 0) {
            return array_merge(['prefix' => $prefix], $info);
        }
    }
    return ['prefix' => 'ALTRO', 'nome' => 'Altro', 'icona' => '📁', 'colore' => '#95a5a6', 'settore' => 'altro', 'classe' => 'altro'];
}

/**
 * Ritorna lo stato in base alla percentuale
 */
function local_coachmanager_get_status($percentage) {
    if ($percentage >= 90) return ['stato' => 'excellent', 'label' => 'Eccellente', 'colore' => '#28a745'];
    if ($percentage >= 70) return ['stato' => 'good', 'label' => 'Buono', 'colore' => '#17a2b8'];
    if ($percentage >= 50) return ['stato' => 'warning', 'label' => 'Attenzione', 'colore' => '#ffc107'];
    return ['stato' => 'critical', 'label' => 'Critico', 'colore' => '#dc3545'];
}

/**
 * Ritorna i livelli Bloom
 */
function local_coachmanager_get_bloom_levels() {
    return [
        1 => ['nome' => 'RICORDO', 'descrizione' => 'Riesco a ricordare le informazioni base', 'colore' => '#e74c3c'],
        2 => ['nome' => 'COMPRENDO', 'descrizione' => 'Comprendo i concetti fondamentali', 'colore' => '#e67e22'],
        3 => ['nome' => 'APPLICO', 'descrizione' => 'Riesco ad applicare le procedure in situazioni standard', 'colore' => '#f1c40f'],
        4 => ['nome' => 'ANALIZZO', 'descrizione' => 'Sono in grado di analizzare situazioni e prendere decisioni', 'colore' => '#27ae60'],
        5 => ['nome' => 'VALUTO', 'descrizione' => 'Posso valutare situazioni complesse e proporre soluzioni', 'colore' => '#3498db'],
        6 => ['nome' => 'CREO', 'descrizione' => 'Sono in grado di creare soluzioni innovative', 'colore' => '#9b59b6'],
    ];
}

/**
 * Pulisce il testo da marker CDATA
 */
function local_coachmanager_clean_text($text) {
    $text = str_replace('<![CDATA[', '', $text);
    $text = str_replace(']]>', '', $text);
    $text = str_replace(']]&gt;', '', $text);
    $text = strip_tags($text);
    return trim($text);
}

/**
 * Calcola i punteggi per area di uno studente
 */
function local_coachmanager_get_student_scores($studentid) {
    global $DB;
    
    $area_map = local_coachmanager_get_area_map();
    $scores = [];
    
    // Inizializza aree
    foreach ($area_map as $prefix => $info) {
        $scores[$info['classe']] = [
            'info' => $info,
            'competenze' => [],
            'totale' => 0,
            'quiz_sum' => 0,
            'quiz_count' => 0,
            'quiz_media' => null,
            'autoval_sum' => 0,
            'autoval_count' => 0,
            'autoval_media' => null,
            'gap' => null
        ];
    }
    
    // Carica competenze
    $competencies = $DB->get_records_sql("
        SELECT c.id, c.shortname, c.description, c.idnumber
        FROM {competency} c
        JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
        WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
    ");
    
    // Organizza per area
    foreach ($competencies as $comp) {
        $area_info = local_coachmanager_get_competency_area($comp->idnumber);
        $area_key = $area_info['classe'];
        
        if (isset($scores[$area_key])) {
            $scores[$area_key]['competenze'][] = $comp;
            $scores[$area_key]['totale']++;
        }
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
    
    // Cerca tabella mapping
    $comp_question_table = null;
    $tables = ['qbank_competenciesbyquestion', 'local_competencymanager_qcomp'];
    foreach ($tables as $table) {
        if ($DB->get_manager()->table_exists($table)) {
            $comp_question_table = $table;
            break;
        }
    }
    
    if ($comp_question_table) {
        // Carica mapping
        $question_competencies = [];
        $mappings = $DB->get_records($comp_question_table);
        foreach ($mappings as $map) {
            if (!isset($question_competencies[$map->questionid])) {
                $question_competencies[$map->questionid] = [];
            }
            $question_competencies[$map->questionid][] = $map->competencyid;
        }
        
        // Calcola punteggi
        foreach ($quiz_results as $result) {
            if (!isset($question_competencies[$result->questionid])) continue;
            
            foreach ($question_competencies[$result->questionid] as $compid) {
                if (!isset($competencies[$compid])) continue;
                
                $comp = $competencies[$compid];
                $area_info = local_coachmanager_get_competency_area($comp->idnumber);
                $area_key = $area_info['classe'];
                
                if (isset($scores[$area_key])) {
                    $score = ($result->fraction !== null) ? floatval($result->fraction) * 100 : 0;
                    $scores[$area_key]['quiz_sum'] += $score;
                    $scores[$area_key]['quiz_count']++;
                }
            }
        }
    }
    
    // Calcola medie e status
    foreach ($scores as $key => &$area) {
        if ($area['quiz_count'] > 0) {
            $area['quiz_media'] = round($area['quiz_sum'] / $area['quiz_count'], 1);
        }
        if ($area['autoval_count'] > 0) {
            $area['autoval_media'] = round($area['autoval_sum'] / $area['autoval_count'], 1);
        }
        if ($area['quiz_media'] !== null && $area['autoval_media'] !== null) {
            $area['gap'] = round($area['autoval_media'] - $area['quiz_media'], 1);
        }
        $area['status'] = local_coachmanager_get_status($area['quiz_media'] ?? 0);
    }
    
    return $scores;
}
