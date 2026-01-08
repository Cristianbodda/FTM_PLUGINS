<?php
// ============================================
// Self Assessment - Capabilities
// ============================================

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    
    // Compilare la propria autovalutazione (studenti)
    'local/selfassessment:complete' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'user' => CAP_ALLOW,
        ],
    ],
    
    // Visualizzare autovalutazioni degli studenti (coach/docenti)
    'local/selfassessment:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    
    // Gestire abilitazioni studenti (abilitare/disabilitare)
    'local/selfassessment:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    
    // Inviare reminder agli studenti
    'local/selfassessment:sendreminder' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    
];
