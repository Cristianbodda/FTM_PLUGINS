<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026040701;  // Fix final_ratings decimal + coach eval permissions
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.0';    // Fix tabella comparativa salvataggio + coach eval can_edit
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
