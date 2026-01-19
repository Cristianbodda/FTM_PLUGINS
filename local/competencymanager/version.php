<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026011601;  // AGGIORNATO: Aggiunta capability managesectors
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.3.0';    // AGGIORNATO: Capability gestione settori per segreteria/coach
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
