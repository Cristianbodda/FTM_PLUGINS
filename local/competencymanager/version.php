<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026021001;  // AGGIORNATO: Valutazioni finali modificabili con storico
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.5.0';    // Valutazioni finali: modifiche manuali con audit trail
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
