<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026051903;  // Passaporto AI: gpt-4.1-mini, vocabolario settore, anti-ripetizione, strategia per fascia
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.5';    // Passaporto AI v4: qualità testi migliorata (specificità + variazione struttura)
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
