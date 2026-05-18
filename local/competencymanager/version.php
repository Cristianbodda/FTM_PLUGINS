<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026051201;  // AI generation per Passaporto Tecnico
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.2';    // AI generation per Passaporto Tecnico
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
