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

    // =========================================================================
    // NUOVO PERMESSO: Gestione settori studenti (segreteria e coach)
    // Permette di visualizzare e modificare i settori primari degli studenti
    // =========================================================================
    'local/competencymanager:managesectors' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,  // Coach
            'manager' => CAP_ALLOW,          // Segreteria
        ],
    ],

    // =========================================================================
    // VALUTAZIONE FORMATORE: Creare e modificare proprie valutazioni
    // Permette al coach di inserire valutazioni sui propri studenti
    // =========================================================================
    'local/competencymanager:evaluate' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,  // Coach
            'manager' => CAP_ALLOW,          // Segreteria
        ],
    ],

    // =========================================================================
    // VALUTAZIONE FORMATORE: Visualizzare tutte le valutazioni
    // Permette di vedere le valutazioni di altri coach
    // =========================================================================
    'local/competencymanager:viewallevaluations' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,  // Altri coach possono vedere
            'manager' => CAP_ALLOW,          // Segreteria
        ],
    ],

    // =========================================================================
    // VALUTAZIONE FORMATORE: Modificare tutte le valutazioni (con tracking)
    // Solo segreteria può modificare valutazioni di altri (viene tracciato)
    // =========================================================================
    'local/competencymanager:editallevaluations' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,  // Solo segreteria/admin
        ],
    ],

    // =========================================================================
    // VALUTAZIONE FORMATORE: Autorizzare studente a vedere la valutazione
    // =========================================================================
    'local/competencymanager:authorizestudentview' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,  // Coach
            'manager' => CAP_ALLOW,          // Segreteria
        ],
    ],

];
