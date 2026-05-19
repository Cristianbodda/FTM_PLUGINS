<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026051902;  // Passaporto: __ORIG baseline (Ripristina), FINAL_NOTE improve/rewrite
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.4';    // Passaporto AI v3: permanent coach baseline + full AI actions on nota finale
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
