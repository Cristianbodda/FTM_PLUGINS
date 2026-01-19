<?php
// ============================================
// CoachManager - Version Information
// ============================================
// Plugin per gestione coach e studenti FTM
// ============================================

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_coachmanager';
$plugin->version = 2025122304;           // YYYYMMDDXX - Dashboard Coach Integrata
$plugin->requires = 2022041900;          // Moodle 4.0+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '2.1.0';              // Aggiunta Dashboard Coach Integrata

// Dipendenze
$plugin->dependencies = [
    'local_competencymanager' => ANY_VERSION,  // Opzionale ma raccomandato
];
