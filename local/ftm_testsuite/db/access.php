<?php
/**
 * Capabilities for FTM Test Suite
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Gestione completa del plugin
    'local/ftm_testsuite:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ],
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS
    ],

    // Esecuzione dei test
    'local/ftm_testsuite:execute' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW
        ],
        'riskbitmask' => RISK_CONFIG
    ],

    // Visualizzazione risultati
    'local/ftm_testsuite:viewresults' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ]
    ]

];
