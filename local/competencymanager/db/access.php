<?php
/**
 * Capability definitions for Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    
    // =========================================================================
    // PERMESSO ESISTENTE: Visualizzazione report
    // =========================================================================
    'local/competencymanager:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],
    
    // =========================================================================
    // PERMESSO ESISTENTE: Gestione generale
    // =========================================================================
    'local/competencymanager:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    
    // =========================================================================
    // NUOVO PERMESSO: Gestione coaching studenti
    // Permette di assegnare studenti ai coach, gestire date misura, 
    // inviare reminder email, gestire progressione 6 settimane
    // =========================================================================
    'local/competencymanager:managecoaching' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,  // Coach/Docente
            'manager' => CAP_ALLOW,          // Segretaria/Admin
        ],
    ],
    
    // =========================================================================
    // NUOVO PERMESSO: Assegnare studenti ai coach (solo segretaria)
    // Permesso speciale per assegnare studenti ai docenti
    // =========================================================================
    'local/competencymanager:assigncoach' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,  // Solo manager/segretaria
        ],
    ],
    
];
