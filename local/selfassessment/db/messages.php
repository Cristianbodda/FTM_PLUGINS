<?php
// ============================================
// Self Assessment - Message Providers
// ============================================
// Definisce i tipi di notifica che il plugin
// può inviare tramite il sistema messaggi Moodle
// Compatibile con Moodle 4.x / 5.x
// ============================================

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Reminder inviato dal coach allo studente
    'reminder' => [
        'capability' => 'local/selfassessment:complete',
    ],

    // Notifica quando nuove competenze vengono assegnate per autovalutazione
    'assignment' => [
        'capability' => 'local/selfassessment:complete',
    ],
];
