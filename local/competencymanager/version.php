<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026042901;  // Fix passport coach_comp query: add courseid filter
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.1';    // Fix passaporto tecnico: valori coach da corso corretto
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
