<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026033101;  // Garage FTM config table for per-student display settings
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.8.0';    // Garage FTM: configurazioni per studente (aree, competenze, overlay)
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
