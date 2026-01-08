<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Estende la navigazione del corso
 */
function local_competencymanager_extend_navigation_course($navigation, $course, $context) {
    global $USER;
    
    // Link per Docenti/Manager - Dashboard Competenze
    if (has_capability('local/competencymanager:view', $context)) {
        $url = new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'local_competencymanager'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'competencymanager',
            new pix_icon('i/competencies', '')
        );
    }
    
    // Link per Studenti - La mia Autovalutazione
    // Verifica se l'utente è iscritto come studente (non docente)
    $isstudent = false;
    if (is_enrolled($context, $USER->id)) {
        // Verifica se NON ha capability di docente
        if (!has_capability('moodle/course:manageactivities', $context)) {
            $isstudent = true;
        }
    }
    
    if ($isstudent) {
        $url = new moodle_url('/local/competencymanager/my_selfassessment.php', ['courseid' => $course->id]);
        $navigation->add(
            '🎯 La mia Autovalutazione',
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'myselfassessment',
            new pix_icon('i/grades', '')
        );
    }
}

/**
 * Estrae l'area dall'idnumber della competenza
 */
function local_competencymanager_get_area_from_idnumber($idnumber) {
    if (empty($idnumber)) return 'Altro';
    $parts = explode('_', $idnumber);
    if (count($parts) >= 2) return $parts[0] . '_' . $parts[1];
    return $idnumber;
}

/**
 * Calcola la percentuale
 */
function local_competencymanager_calculate_percentage($correct, $total) {
    if ($total <= 0) return 0;
    return round(($correct / $total) * 100, 1);
}

/**
 * Get Bloom levels with translations
 */
function local_competencymanager_get_bloom_levels($lang = 'it') {
    $levels = [
        1 => ['it' => 'Ricordare', 'en' => 'Remember', 'de' => 'Erinnern'],
        2 => ['it' => 'Comprendere', 'en' => 'Understand', 'de' => 'Verstehen'],
        3 => ['it' => 'Applicare', 'en' => 'Apply', 'de' => 'Anwenden'],
        4 => ['it' => 'Analizzare', 'en' => 'Analyze', 'de' => 'Analysieren'],
        5 => ['it' => 'Valutare', 'en' => 'Evaluate', 'de' => 'Bewerten'],
        6 => ['it' => 'Creare', 'en' => 'Create', 'de' => 'Erschaffen']
    ];
    $result = [];
    foreach ($levels as $num => $translations) {
        $result[$num] = isset($translations[$lang]) ? $translations[$lang] : $translations['en'];
    }
    return $result;
}

/**
 * Get Bloom level details (colori, descrizioni)
 */
function local_competencymanager_get_bloom_details() {
    return [
        1 => ['name' => 'Ricordare', 'color' => '#e74c3c', 'bg' => '#ffeaea', 'desc' => 'Recuperare conoscenze dalla memoria a lungo termine'],
        2 => ['name' => 'Comprendere', 'color' => '#e67e22', 'bg' => '#fff5e6', 'desc' => 'Costruire significato da messaggi orali, scritti e grafici'],
        3 => ['name' => 'Applicare', 'color' => '#f1c40f', 'bg' => '#fffce6', 'desc' => 'Eseguire o utilizzare una procedura in una situazione data'],
        4 => ['name' => 'Analizzare', 'color' => '#27ae60', 'bg' => '#e8f8e8', 'desc' => 'Scomporre il materiale in parti e determinare le relazioni'],
        5 => ['name' => 'Valutare', 'color' => '#3498db', 'bg' => '#e8f4fc', 'desc' => 'Esprimere giudizi basati su criteri e standard'],
        6 => ['name' => 'Creare', 'color' => '#9b59b6', 'bg' => '#f3e8fc', 'desc' => 'Mettere insieme elementi per formare un nuovo insieme']
    ];
}
