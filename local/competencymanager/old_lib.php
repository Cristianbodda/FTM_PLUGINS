<?php
defined('MOODLE_INTERNAL') || die();

function local_competencymanager_extend_navigation_course($navigation, $course, $context) {
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
}

function local_competencymanager_get_area_from_idnumber($idnumber) {
    if (empty($idnumber)) return 'Altro';
    $parts = explode('_', $idnumber);
    if (count($parts) >= 2) return $parts[0] . '_' . $parts[1];
    return $idnumber;
}

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
