<?php
// ============================================
// FTM Hub - Capabilities
// ============================================

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    
    // Visualizzare l'hub
    'local/ftm_hub:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    
];
