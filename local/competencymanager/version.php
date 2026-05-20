<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026052001;  // Passaporto AI: Approva Passaporto + few-shot da passaporti approvati dai coach
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.6';    // Passaporto Tecnico v5: approvazione coach + AI calibrata su esempi reali
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
