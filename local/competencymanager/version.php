<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026021101;  // AGGIORNATO: Sistema ponderazione pesi configurabili
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.6.0';    // Ponderazione: pesi configurabili per area/competenza
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
