<?php
// ============================================
// FTM Hub - Library Functions
// ============================================

defined('MOODLE_INTERNAL') || die();

/**
 * Aggiunge link nel menu di navigazione
 */
function local_ftm_hub_extend_navigation(global_navigation $navigation) {
    global $USER, $COURSE;
    
    if (!isloggedin() || isguestuser()) {
        return;
    }
    
    // Aggiungi link all'hub nella navigazione
    $courseid = $COURSE->id > 1 ? $COURSE->id : 0;
    $params = $courseid > 0 ? ['courseid' => $courseid] : [];
    
    $node = $navigation->add(
        get_string('pluginname', 'local_ftm_hub'),
        new moodle_url('/local/ftm_hub/index.php', $params),
        navigation_node::TYPE_CUSTOM,
        null,
        'ftm_hub',
        new pix_icon('i/settings', '')
    );
}

/**
 * Aggiunge link nelle impostazioni del corso
 */
function local_ftm_hub_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;
    
    // Solo per contesto corso
    if ($context->contextlevel != CONTEXT_COURSE) {
        return;
    }
    
    // Verifica permessi
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }
    
    // Aggiungi sotto "Course administration"
    if ($coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        $courseid = $context->instanceid;
        
        $node = $coursenode->add(
            get_string('pluginname', 'local_ftm_hub'),
            new moodle_url('/local/ftm_hub/index.php', ['courseid' => $courseid]),
            navigation_node::TYPE_SETTING,
            null,
            'ftm_hub_course',
            new pix_icon('i/settings', '')
        );
    }
}
